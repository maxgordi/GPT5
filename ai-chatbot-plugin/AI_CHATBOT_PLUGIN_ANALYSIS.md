# AI ChatBot Plugin Analysis

## 1. Bootstrap Lifecycle
- Entry point: `ai-chatbot-plugin/ai-chatbot.php` instantiates `AIChatBot` on load.
- Defines URL/path constants but does **not** define `AI_CHATBOT_PLUGIN_FILE`; classes in `includes/` expect it, so asset URLs fall back to `undefined` (bug).
- Upon activation registers many `ai_chatbot_*` options (OpenAI key/model, UI strings, styling choices, language, feature toggles).
- Hooks registered:
  - Frontend: `wp_enqueue_scripts`, `wp_footer`.
  - AJAX: chat messages, session end, realtime admin settings for both logged-in and guests.
  - Admin: menu plus settings registration, option page assets.
  - Cron: custom `every_minute` schedule used by chat history cleanup.
- Initializes `AI_ChatBot_Chat_History` on `init`, enabling filesystem transcripts and cron-based maintenance.

## 2. Frontend Widget and UX
- `templates/chat-widget.php` outputs the floating launcher and chat window; strings come from saved options.
- `assets/js/chatbot.js` (about 800 lines) controls the UI:
  - Toggle animation, autofocus, message rendering with light markdown rules, typing indicator, scroll management.
  - Persists conversation in `localStorage` (falls back to `sessionStorage`) to restore state and avoid resending history.
  - Tracks inactivity via `INACTIVITY_TIMEOUT`; on timeout, pushes history to the backend for email dispatch.
  - Input hygiene: trims whitespace, capitalizes first letter, limits message length, blocks double sends.
  - Sends AJAX POST (`ai_chatbot_message`) with nonce and session id, surfaces friendly Russian error messages on failure.
  - Uses `navigator.sendBeacon` during `beforeunload` as a last resort to send unsaved history.
  - Adds extras such as online badge, notification bubble, optional audio cues.
- `assets/css/chatbot.css` defines base styling; generated CSS overrides from section 6 adjust colors/sizing at runtime.

## 3. Chat Processing Flow
1. Browser posts sanitized message plus session id to `AIChatBot::handle_chat_message()`.
2. Handler checks nonce, ensures OpenAI key exists, saves user message through `AI_ChatBot_Chat_History::save_message()` (JSON transcript and `.meta`).
3. Calls `call_openai_api()` which wraps `wp_remote_request()` to OpenAI `/v1/chat/completions`, using stored system prompt, selected model, temperature 0.7.
4. Bot reply is stored alongside the conversation and returned via `wp_send_json_success()`.
5. Chat history module schedules `ai_chatbot_check_chat` so inactivity triggers email delivery and eventual cleanup.

## 4. Chat History Subsystem (`includes/class-chat-history.php`)
- Persists sessions under `wp-content/uploads/ai-chatbot-history/` as `{session}.chat` plus `{session}.meta` (timestamps, retry counter, email flag).
- Creates directory and `.htaccess` (`Deny from all`) on first run to block direct downloads.
- Hooks:
  - `ai_chatbot_check_chat`: emails transcript after inactivity timeout and clears files if already stale.
  - `ai_chatbot_cleanup_chats`: cron job every minute that scans `.meta` files, forces pending emails, and retries failures up to five times every five minutes.
- `handle_session_end` AJAX route lets the frontend force email send and delete artifacts when the user finishes chatting.

## 5. Email Pipeline (`includes/email-handler.php`)
- AJAX action `ai_chatbot_send_email` accepts serialized history from the browser.
- Builds plaintext body with site name, timestamps, sender labels, and visitor metadata (IP, user agent, referrer).
- Default recipient is `gordienko.office@gmail.com`; configurable via `ai_chatbot_email_to` option.
- Uses PHP `mail()` with handcrafted headers instead of WordPress `wp_mail()`, unlike the history class which relies on `wp_mail()`.

## 6. Styling and Asset Helpers
- `AI_ChatBot_CSS_Generator` compiles CSS fragments from options (color scheme, fonts, margins, widget sizes) and writes them to `uploads/ai-chatbot/ai-chatbot-<timestamp>.css`, bumping `ai_chatbot_style_version` and pruning stale files.
- `AI_Chatbot_Dynamic_Styles` can emit inline `<style>` blocks and save CSS variables; `dynamic-styles-loader.php` hooks both `wp_head` and `admin_head`, and regenerates files when `ai_chatbot_options` change.
- `AI_ChatBot_Assets` enqueues frontend and admin resources with cache-busting version numbers, but it references `AI_CHATBOT_PLUGIN_FILE`, which is never defined, so enqueue URLs break.
- `AI_ChatBot_Cache_Handler` exposes AJAX cache clearing: deletes transients, flushes object cache, resets PHP opcache when available, increments style version.

## 7. Admin Experience (`admin/settings-page.php`)
- Custom settings page (non standard Settings API layout) provides sections for:
  - General enable toggle, bot name, localized strings, welcome and system prompts, OpenAI credentials.
  - Appearance: color scheme with custom palette, margins, widget/window/avatar size, animation choice, font family and size.
  - Email routing address and inactivity timeout.
  - Live preview markup synced with `assets/js/admin-realtime.js`, which serializes form values and posts to `ai_chatbot_save_realtime_settings` for instant persistence and CSS regeneration.
- Uses `wp_nonce_field` and direct `update_option` calls; after save, regenerates CSS via `AI_ChatBot_CSS_Generator`.
- Enqueues `assets/js/admin-settings.js`, but that file is missing from the repository (script 404).

## 8. Telegram Hooks
- Duplicate implementations exist (`class-telegram.php`, `class-telegram-handler.php`, `telegram-functions.php`) that register the same token/chat id settings and expose `send_message()` helpers hitting Telegram `sendMessage` with non-blocking HTTP requests.
- Settings form (`admin/telegram-settings.php`) saves `ai_chatbot_telegram_token` and `ai_chatbot_telegram_chat_id`.
- Chat workflow does not currently call these helpers, so Telegram notifications appear unfinished.

## 9. File Layout Notes
- In addition to the main plugin folder, there is a second copy at `assets/ai-chatbot-plugin/` containing its own `ai-chatbot.php` and assets (likely packaging artifact).
- Root also holds a legacy `chat-widget.php` duplicate alongside the template-based version.
- Assets folder groups admin CSS/JS, frontend CSS/JS, and images; admin JS mismatch noted above.

## 10. Observed Risks and Gaps
- Missing constants (`AI_CHATBOT_PLUGIN_FILE`, `AI_CHATBOT_PLUGIN_DIR` usage in `ai-chatbot.php`) will generate notices and break asset URLs or realtime CSS updates.
- Source text suffers from character encoding issues (mojibake), so many Russian strings may render incorrectly on the site.
- Mixed email senders (`mail()` vs `wp_mail()`), redundant Telegram code, and duplicated plugin directories complicate maintenance.
- Spam detection hooks mentioned in `class-chat-handler.php` are not wired into `AIChatBot::handle_chat_message()`, leaving abuse unmitigated.
- Frontend localization registers `ai_chatbot_ajax`, but script references `ai_chatbot_options` when reading inactivity timeout, causing runtime errors.
- Activation hook seeds some options but misses others (`ai_chatbot_margin`, etc.), and registers `ai_chatbot_enabled` twice.

## 11. Runtime Sequence Snapshot
1. Plugin loads, registers hooks, instantiates chat history (which schedules cron if needed).
2. Visitor loads a page, assets enqueue, chat widget markup prints in `wp_footer`.
3. User opens widget; JS restores saved history, shows welcome message, listens for input.
4. Sending a message hits the AJAX endpoint, which saves the message, calls OpenAI, returns the reply, and updates the UI.
5. Inactivity timer or explicit close triggers session-end logic; backend emails transcript and removes cached files.
6. Admin changes settings; realtime AJAX saves options, CSS regenerates, new styles apply on next page load.

## 12. Suggested Follow-ups
- Define `AI_CHATBOT_PLUGIN_FILE` (or switch asset loaders to `AI_CHATBOT_PLUGIN_PATH`) and update realtime CSS loader to use existing constants.
- Fix encoding, move strings into translation functions, and provide proper language files.
- Standardize on `wp_mail()` and add error logging for delivery failures.
- Remove redundant plugin copy and consolidate Telegram integration to one implementation invoked from the chat flow.
- Either include `assets/js/admin-settings.js` or remove the enqueue call.
- Localize full frontend settings (provide `ai_chatbot_options.inactivity_timeout`) so JS runs without errors.
- Wire spam filtering, add rate limiting, and improve error handling around OpenAI and cron retries.

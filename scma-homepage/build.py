"""SCMA-KVK homepage, direction: stencil cargo marking on steel (Impeccable).
Usage: python3 build.py <scratch_dir_with_contact_form.txt> <out_file>
Produces Divi shortcode content for the WordPress homepage."""
import sys, re, html
SCR, OUT = sys.argv[1], sys.argv[2]
U = 'https://scma-kvk.com.ua/wp-content/uploads/'
def e(s): return html.escape(s, quote=True)

GUARD = ("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='22' viewBox='0 0 120 22'%3E"
         "%3Cpath d='M8 22 L20 2 H96 L108 22Z' fill='%23E58D00'/%3E%3Cpath d='M20 2 H96 L98 5 H18Z' fill='%23ffb23a' opacity='.55'/%3E%3C/svg%3E")
NOISE = ("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='220' height='220'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.85' numOctaves='3' stitchTiles='stitch'/%3E%3CfeColorMatrix values='0 0 0 0 1 0 0 0 0 1 0 0 0 0 1 0 0 0 .07 0'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E")

CSS = r"""
.kv{--steel:#16181b;--steel2:#25292d;--seam:#33383d;--paint:#ecebe5;--dim:#b4b8bc;--or:#E58D00;--or2:#ffa31a;--conc:#c2bfb8;--ink:#141618;--inkdim:#3e4246;
--disp:'Sofia Sans Extra Condensed','Arial Narrow',sans-serif;--body:'Onest',system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;--mono:'Martian Mono',ui-monospace,monospace;
font-family:var(--body);font-size:clamp(16px,.35vw + 14.6px,19px);line-height:1.6;color:var(--paint);background:var(--steel)}
.kv *{box-sizing:border-box}.kv p{margin:0 0 1em;padding:0}.kv a{color:inherit;text-decoration:none}
.kv ::selection{background:var(--or);color:var(--ink)}
.kv a:focus-visible,.kv button:focus-visible{outline:3px solid var(--or);outline-offset:4px;border-radius:2px}
.kv h1,.kv h2,.kv h3,.kv h4{margin:0;padding:0;color:inherit;font-weight:800}
.kv-w{width:min(1360px,92vw);margin:0 auto}
/* steel and concrete grounds */
.kv-steel{background-color:var(--steel);background-image:url("NOISE"),linear-gradient(90deg,transparent calc(50% - 1px),rgba(0,0,0,.35) calc(50% - 1px),rgba(0,0,0,.35) 50%,rgba(255,255,255,.04) 50%,transparent calc(50% + 1px));background-size:220px 220px,100% 100%;color:var(--paint)}
.kv-conc{background-color:var(--conc);background-image:url("NOISE");background-size:220px;color:var(--ink)}
.kv-orange{background-color:var(--or);background-image:url("NOISE");background-size:220px;color:var(--ink)}
.kv-sec{padding:clamp(72px,8vw,136px) 0;position:relative}
.kv-guard{height:22px;background:var(--ink) url("GUARD") repeat-x left center/120px 22px}
/* stencil marking */
.kv-st{font-family:var(--disp);font-weight:900;text-transform:uppercase;letter-spacing:.01em;line-height:.92;filter:url(#kvspray)}
.kv h2.kv-st{font-size:clamp(44px,5.4vw,96px);margin-bottom:.35em;max-width:16ch;text-wrap:balance}
.kv h3{font-family:var(--disp);font-weight:800;text-transform:uppercase;font-size:clamp(24px,1.8vw,32px);letter-spacing:.02em;line-height:1}
.kv-lead{font-size:clamp(18px,1.15vw,22px);max-width:56ch;color:var(--dim)}
.kv-conc .kv-lead,.kv-orange .kv-lead{color:var(--inkdim)}
.kv-m{font-family:var(--mono);font-size:.78em;letter-spacing:.02em}
/* actions */
.kv-b{display:inline-flex;align-items:center;gap:.6em;font-family:var(--disp);font-weight:800;text-transform:uppercase;font-size:clamp(20px,1.4vw,26px);letter-spacing:.03em;padding:.62em 1.1em .58em;border:3px solid var(--or);background:var(--or);color:var(--ink)!important;transition:background .2s,color .2s,box-shadow .2s;box-shadow:0 6px 16px rgba(0,0,0,.28)}
.kv-b:hover{background:var(--or2);border-color:var(--or2);box-shadow:0 10px 24px rgba(0,0,0,.34)}
.kv-b svg{width:.9em;height:.9em;flex:none}
.kv-b.ln{background:transparent;color:var(--paint)!important;border-color:var(--paint);box-shadow:none}
.kv-b.ln:hover{background:var(--paint);color:var(--ink)!important}
.kv-conc .kv-b.ln,.kv-orange .kv-b.ln{color:var(--ink)!important;border-color:var(--ink)}
.kv-conc .kv-b.ln:hover,.kv-orange .kv-b.ln:hover{background:var(--ink);color:var(--paint)!important}
.kv-orange .kv-b{background:var(--ink);border-color:var(--ink);color:var(--paint)!important}.kv-orange .kv-b.ln{background:transparent;color:var(--ink)!important}.kv-orange .kv-b.ln:hover{background:var(--ink);color:var(--paint)!important}
.kv-acts{display:flex;flex-wrap:wrap;gap:14px;margin-top:clamp(24px,2.4vw,40px)}
.kv-ul{border-bottom:3px solid var(--or);font-weight:600;padding-bottom:2px}
.kv-ul:hover{color:var(--or)!important}
.kv-conc .kv-ul:hover{color:#9a5d00!important}
/* hero */
.kv-hero .kv-w{display:grid;grid-template-columns:1.7fr 1fr;gap:clamp(28px,3.4vw,64px);align-items:center;padding:clamp(28px,3vw,48px) 0 clamp(24px,2.4vw,36px)}
.kv-hero h1{font-size:clamp(46px,5.9vw,116px);color:var(--paint)}.kv-hero h1 .l1{display:block;white-space:nowrap}
.kv-hero h1 .o{color:var(--or);display:block}
.kv-hero .sub{font-family:var(--body);font-size:clamp(16px,1.05vw,19px);color:var(--dim);margin:.8em 0 0;max-width:52ch;text-transform:none;filter:none}
.kv-data{margin:clamp(16px,1.6vw,26px) 0 0;border-top:2px solid var(--paint);max-width:640px}
.kv-data div{display:grid;grid-template-columns:1fr auto;align-items:baseline;gap:16px;padding:.34em 0;border-bottom:1px solid #4a4f55}
.kv-data dt{font-family:var(--body);font-weight:600;font-size:13px;letter-spacing:.1em;text-transform:uppercase;color:var(--dim);margin:0}
.kv-data dd{margin:0;text-transform:uppercase;font-variant-numeric:tabular-nums;font-family:var(--disp);font-weight:800;font-size:clamp(22px,1.7vw,32px);letter-spacing:.02em;color:var(--paint)}
.kv-data dd b{color:var(--or);font-weight:900}
.kv-hatch{position:relative;border:10px solid var(--steel2);outline:1px solid var(--seam);box-shadow:inset 0 0 0 1px #000,0 30px 60px rgba(0,0,0,.45);aspect-ratio:4/5;max-height:calc(100vh - 200px);background:#000}
.kv-hatch img{width:100%;height:100%;object-fit:cover;display:block}
.kv-hatch:before{content:'';position:absolute;inset:-10px;pointer-events:none;background:radial-gradient(circle at 10px 10px,#5b6167 0 3px,#202326 3.5px 5px,transparent 5.5px) 0 0/100% 100% no-repeat,radial-gradient(circle at calc(100% - 10px) 10px,#5b6167 0 3px,#202326 3.5px 5px,transparent 5.5px),radial-gradient(circle at 10px calc(100% - 10px),#5b6167 0 3px,#202326 3.5px 5px,transparent 5.5px),radial-gradient(circle at calc(100% - 10px) calc(100% - 10px),#5b6167 0 3px,#202326 3.5px 5px,transparent 5.5px)}
.kv-hatch figcaption{position:absolute;left:0;bottom:0;background:var(--steel);color:var(--dim);font-family:var(--body);font-weight:600;font-size:12px;letter-spacing:.1em;padding:8px 12px;text-transform:uppercase}
/* seam-crossing marking */
.kv-cross{position:relative;height:0;overflow:visible;z-index:2;pointer-events:none}
.kv-cross span{position:absolute;right:4vw;top:0;transform:translateY(-58%);font-family:var(--disp);font-weight:900;font-size:clamp(120px,19vw,340px);line-height:.8;color:rgba(229,141,0,.6);letter-spacing:.02em;filter:url(#kvspray)}.kv-hero{position:relative;z-index:3}.kv-cross{z-index:1}
/* catalog grid (marking plate) */
.kv-cat-head{display:grid;grid-template-columns:1fr auto;align-items:end;gap:24px;margin-bottom:clamp(28px,3vw,48px)}
.kv-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:2px;background:var(--ink);border:2px solid var(--ink)}
.kv-cell{position:relative;background:#e2dfd8;display:flex;flex-direction:column;min-height:100%}
.kv-cell .im{aspect-ratio:16/10;overflow:hidden;background:#cfccc4}
.kv-cell img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .6s cubic-bezier(.2,.7,.2,1)}
.kv-cell .bd{padding:14px 16px 18px;display:flex;flex-direction:column;gap:8px;flex:1}
.kv-cell .ty{font-family:var(--body);font-size:14px;color:#4a4e52}
.kv-cell h3{font-family:var(--disp);font-weight:800;text-transform:uppercase;font-size:clamp(22px,1.5vw,28px);line-height:.95;letter-spacing:.02em;color:var(--ink)}
.kv-cell h3 a:after{content:'';position:absolute;inset:0}
.kv-cell:hover{background:#fff}.kv-cell:hover img{transform:scale(1.05)}
.kv-cell:hover h4{color:#000;text-decoration:underline;text-decoration-color:var(--or);text-decoration-thickness:3px;text-underline-offset:4px}
.kv-cell:focus-within{outline:3px solid var(--or);outline-offset:-3px}
/* stock + steps */
.kv-split{display:grid;grid-template-columns:1fr 1fr;gap:clamp(32px,5vw,96px);align-items:start}
.kv-photo{border:8px solid var(--steel2);outline:1px solid var(--seam);box-shadow:0 24px 48px rgba(0,0,0,.4)}
.kv-photo img{width:100%;height:auto;display:block}
.kv-steps{list-style:none!important;margin:clamp(40px,4vw,64px) 0 0!important;padding:0!important;display:grid;grid-template-columns:repeat(5,1fr);border-top:2px solid var(--paint);counter-reset:s}
.kv-steps li{list-style:none!important;margin:0!important;padding:18px 18px 0 0!important;counter-increment:s;border-right:1px solid #4a4f55}
.kv-steps li:last-child{border-right:0}
.kv-steps li:before{content:counter(s);display:block;font-family:var(--disp);font-weight:900;font-size:56px;line-height:.8;color:var(--or);margin-bottom:12px}
.kv-steps b{display:block;font-family:var(--disp);font-weight:800;text-transform:uppercase;font-size:24px;letter-spacing:.02em;line-height:1;margin-bottom:8px}
.kv-steps span{display:block;font-size:15px;color:var(--dim);line-height:1.5}
/* why */
.kv-why{display:grid;grid-template-columns:repeat(4,1fr);gap:0;margin-top:clamp(28px,3vw,48px);border-top:2px solid var(--paint)}
.kv-why div{padding:22px 22px 0 0;border-right:1px solid #4a4f55}
.kv-why div+div{padding-left:22px}.kv-why div:last-child{border-right:0}
.kv-why p{color:var(--dim);margin:12px 0 0;font-size:16px}
/* compensation */
.kv-comp .kv-w{display:grid;grid-template-columns:auto 1fr;gap:clamp(28px,5vw,96px);align-items:center}
.kv-comp{overflow:hidden}.kv-comp .big{font-size:clamp(150px,22vw,380px);color:var(--ink);line-height:.78;--lh:.78em}
.kv-comp h2.kv-st{color:var(--ink)}
.kv-comp p{color:#2b2208;font-size:clamp(17px,1.1vw,21px);max-width:52ch}
/* service */
.kv-svc{display:grid;grid-template-columns:1fr 1fr;min-height:clamp(480px,42vw,720px)}
.kv-svc .ph{background:#000 center/cover no-repeat}
.kv-svc .tx{padding:clamp(48px,6vw,112px) clamp(24px,5vw,96px);display:flex;flex-direction:column;justify-content:center}
.kv-svc h2.kv-st{font-size:clamp(40px,4.2vw,76px)}
/* history */
.kv-hist .kv-w{display:grid;grid-template-columns:auto 1fr;gap:clamp(32px,5vw,96px);align-items:start}
.kv-hist .big{font-size:clamp(160px,20vw,360px);color:var(--or);line-height:.78;--lh:.78em}
.kv-nums{display:grid;grid-template-columns:1fr 1fr;border-top:2px solid var(--paint);margin:28px 0}
.kv-nums div{padding:16px 16px 0 0;font-size:15px;color:var(--dim)}
.kv-nums b{display:block;font-family:var(--disp);font-weight:900;font-size:clamp(48px,4vw,72px);line-height:.9;color:var(--paint)}
/* clients */
.kv-logos{display:grid;grid-template-columns:repeat(7,1fr);gap:2px;background:var(--ink);border:2px solid var(--ink);margin-top:clamp(28px,3vw,48px)}
.kv-logos div{background:#e2dfd8;aspect-ratio:3/2;display:flex;align-items:center;justify-content:center;padding:14%}
.kv-logos img{max-width:100%;max-height:100%;object-fit:contain;filter:grayscale(1) contrast(1.3) brightness(1.18);mix-blend-mode:multiply}
.kv-q{margin:clamp(48px,5vw,88px) 0 0;padding:0;border:0;max-width:58ch}
.kv-q p{font-size:clamp(22px,1.7vw,32px);line-height:1.35;color:var(--ink);font-weight:500;margin:0 0 16px;text-wrap:pretty}
.kv-q cite{font-style:normal;font-family:var(--body);font-weight:600;font-size:14px;letter-spacing:.08em;text-transform:uppercase;color:var(--inkdim)}
/* media */
.kv-vids{display:grid;grid-template-columns:1fr 1fr;gap:clamp(16px,2vw,28px);margin-top:clamp(28px,3vw,48px)}
.kv-yt{position:relative;aspect-ratio:16/9;background:#000 center/cover no-repeat;border:8px solid var(--steel2);outline:1px solid var(--seam);cursor:pointer;padding:0;width:100%;display:block}
.kv-yt:after{content:'';position:absolute;inset:0;background:linear-gradient(0deg,rgba(0,0,0,.6),transparent 55%)}
.kv-yt i{position:absolute;left:16px;bottom:16px;z-index:1;display:flex;align-items:center;gap:14px;background:var(--steel);padding:0 16px 0 0;font-style:normal;font-family:var(--disp);font-weight:800;text-transform:uppercase;font-size:26px;color:var(--paint)}
.kv-yt i:before{content:'';width:58px;height:58px;background:var(--or) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath d='M8 5v14l11-7z' fill='%23141618'/%3E%3C/svg%3E") center/26px no-repeat}
.kv-yt:hover i:before{background-color:var(--or2)}
.kv-yt iframe{position:absolute;inset:0;width:100%;height:100%;border:0;z-index:2}
.kv-gal{display:grid;grid-template-columns:repeat(6,1fr);grid-auto-rows:clamp(140px,12vw,240px);gap:2px;background:var(--ink);border:2px solid var(--ink);margin-top:clamp(28px,3vw,48px)}
.kv-gal a{display:block;overflow:hidden}
.kv-gal a:nth-child(1){grid-column:span 3;grid-row:span 2}.kv-gal a:nth-child(2),.kv-gal a:nth-child(3){grid-column:span 3}.kv-gal a:nth-child(n+4){grid-column:span 2}
.kv-gal img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .6s cubic-bezier(.2,.7,.2,1)}
.kv-gal a:hover img{transform:scale(1.05)}
.kv-docs{display:grid;grid-template-columns:repeat(5,1fr);gap:2px;background:var(--ink);border:2px solid var(--ink);margin-top:clamp(28px,3vw,48px)}
.kv-docs a{display:block;background:#fff;padding:10px}
.kv-docs img{width:100%;height:auto;aspect-ratio:400/516;object-fit:cover;display:block}
.kv-docs a:hover{background:var(--or)}
.kv-end .kv-w{display:grid;grid-template-columns:1.3fr 1fr;gap:clamp(32px,5vw,96px)}
.kv-end .side{border-top:2px solid var(--ink);padding-top:22px}
/* news (Divi blog) */
.kv-news-sec{background:#c2bfb8!important;background-image:url("NOISE")!important;padding:0 0 clamp(72px,8vw,136px)!important}
.kv-news-sec .et_pb_row{width:min(1360px,92vw)!important;max-width:none!important;padding:0!important}
.kv-news .et_pb_ajax_pagination_container{display:grid!important;grid-template-columns:repeat(3,1fr);gap:2px;background:#141618;border:2px solid #141618}
.kv-news .et_pb_post{margin:0!important;padding:0 0 20px!important;border:0!important;background:#e2dfd8!important}
.kv-news .et_pb_post.sticky{display:none!important}
.kv-news .et_pb_image_container,.kv-news .entry-featured-image-url{margin:0 0 16px!important;display:block}
.kv-news .entry-featured-image-url{margin:0!important;aspect-ratio:16/10;overflow:hidden;display:block}
.kv-news .entry-featured-image-url img{width:100%;height:100%;object-fit:cover}
.kv-news .entry-title{margin-top:14px!important;font-family:'Sofia Sans Extra Condensed',sans-serif!important;font-weight:800!important;text-transform:uppercase;font-size:clamp(24px,1.7vw,30px)!important;line-height:.98!important;padding:0 18px 8px!important;letter-spacing:.01em}
.kv-news .entry-title a{color:#141618!important}
.kv-news .post-meta{padding:0 18px!important;font-family:'Onest',sans-serif;font-weight:600;font-size:12px!important;letter-spacing:.05em;text-transform:uppercase;color:#5a5e62!important}
.kv-news .post-content{padding:8px 18px 0!important;font-family:'Onest',sans-serif;font-size:15px;color:#3e4246}
.kv-news .more-link{display:none!important}
/* contact */
.kv-contact-sec{background:#16181b!important;padding:clamp(72px,8vw,136px) 0!important}
.kv-contact-sec .et_pb_row{width:min(1360px,92vw)!important;max-width:none!important;padding:0!important}
.kv-contact-sec .et_pb_contact_form_container{background:#ecebe5;padding:clamp(20px,2.4vw,36px)!important;border:4px solid #E58D00}
.kv-contact-sec input,.kv-contact-sec textarea{border:2px solid #141618!important;background:#fff!important;border-radius:0!important;color:#141618!important;font-family:'Onest',sans-serif!important;font-size:16px!important}
.kv-contact-sec input:focus,.kv-contact-sec textarea:focus{border-color:#E58D00!important;outline:none}
#page-container .kv-contact-sec .et_pb_contact_form_container .et_pb_contact_submit,body #page-container .et_pb_section.kv-contact-sec .et_pb_contact_form .et_pb_contact_submit.et_pb_button{background:#E58D00!important;border:3px solid #E58D00!important;color:#141618!important;border-radius:0!important;font-family:'Sofia Sans Extra Condensed',sans-serif!important;font-weight:800!important;font-size:24px!important;letter-spacing:.04em!important;text-transform:uppercase!important;padding:8px 26px!important}
#page-container .kv-contact-sec .et_pb_contact_form_container .et_pb_contact_submit:hover{background:#ffa31a!important}
.kv-contact-sec .et_contact_bottom_container{float:none!important;display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:flex-end;margin-top:8px}.kv-contact-sec .et_pb_contact_right{float:none!important;margin:0!important}.kv-contact-sec .et_pb_contact_right,.kv-contact-sec .et_pb_contact_right p{color:#141618!important;font-size:18px!important;font-weight:600}.kv-contact-sec .et_pb_contact_captcha_question{color:#141618!important;font-family:'Onest',sans-serif;font-weight:600;font-size:18px!important}.kv-contact-sec .et_pb_contact_captcha{width:72px!important}.kv.kv-ci{background:transparent!important}
.kv-ci .tels a{display:block;font-family:var(--disp);font-weight:900;font-size:clamp(40px,3.6vw,64px);line-height:1;color:var(--paint)!important;margin-bottom:6px}
.kv-ci .tels a:hover{color:var(--or)!important}
.kv-ci .nav{display:flex;flex-wrap:wrap;gap:10px 24px;margin-top:32px;padding-top:20px;border-top:1px solid #4a4f55;font-family:var(--body);font-weight:600;font-size:13px;letter-spacing:.1em;text-transform:uppercase}
.kv-ci .nav a{color:var(--dim)!important}.kv-ci .nav a:hover{color:var(--paint)!important}
/* empty paragraph Divi/wpautop can leave before the first section */
.et_builder_inner_content>p:empty{display:none}
/* motion: one focal sequence on the hero (paint sprayed through the marking, hatch opens, data rows stamped), two supporting moments (steps in order, -25% stamp) */
.kv-hero .paint{display:block}
.kv-js .kv-hero .paint{-webkit-mask-image:linear-gradient(90deg,#000 0 42%,transparent 58%);mask-image:linear-gradient(90deg,#000 0 42%,transparent 58%);-webkit-mask-size:260% 100%;mask-size:260% 100%;-webkit-mask-position:100% 0;mask-position:100% 0;transition:-webkit-mask-position .95s cubic-bezier(.45,.05,.25,1) var(--d,0s),mask-position .95s cubic-bezier(.45,.05,.25,1) var(--d,0s)}
.kv-js .kv-hero.go .paint{-webkit-mask-position:0 0;mask-position:0 0}
.kv-js .kv-hatch{clip-path:inset(0 0 100% 0);transition:clip-path .8s cubic-bezier(.16,1,.3,1) .25s}
.kv-js .kv-hero.go .kv-hatch{clip-path:inset(-20px -20px -20px -20px)}
.kv-js .kv-hatch img{transform:scale(1.08);transition:transform 1.6s cubic-bezier(.16,1,.3,1) .25s}
.kv-js .kv-hero.go .kv-hatch img{transform:none}
.kv-js .kv-hero .sub{opacity:0;transition:opacity .5s ease-out .7s}
.kv-js .kv-hero.go .sub{opacity:1}
.kv-js .kv-hero .kv-data div{opacity:0;transform:translateX(-10px);transition:opacity .28s ease-out,transform .28s cubic-bezier(.16,1,.3,1)}
.kv-js .kv-hero.go .kv-data div{opacity:1;transform:none}
.kv-js .kv-hero.go .kv-data div:nth-child(1){transition-delay:.85s}.kv-js .kv-hero.go .kv-data div:nth-child(2){transition-delay:.95s}.kv-js .kv-hero.go .kv-data div:nth-child(3){transition-delay:1.05s}.kv-js .kv-hero.go .kv-data div:nth-child(4){transition-delay:1.15s}
.kv-js .kv-hero .kv-acts{opacity:0;transform:translateY(8px);transition:opacity .35s ease-out .9s,transform .45s cubic-bezier(.16,1,.3,1) .9s}
.kv-js .kv-hero.go .kv-acts{opacity:1;transform:none}
.kv-js .kv-steps li{opacity:0;transform:translateY(12px);transition:opacity .35s ease-out,transform .45s cubic-bezier(.16,1,.3,1)}
.kv-js .kv-steps.in li{opacity:1;transform:none}
.kv-js .kv-steps.in li:nth-child(2){transition-delay:.09s}.kv-js .kv-steps.in li:nth-child(3){transition-delay:.18s}.kv-js .kv-steps.in li:nth-child(4){transition-delay:.27s}.kv-js .kv-steps.in li:nth-child(5){transition-delay:.36s}
.kv-js .kv-stamp{opacity:0;transform:scale(1.12) rotate(-2deg);filter:url(#kvspray) blur(6px)}
.kv-js .kv-stamp.in{opacity:1;transform:none;filter:url(#kvspray) blur(0px);transition:opacity .18s ease-out,transform .42s cubic-bezier(.2,1.2,.4,1),filter .3s ease-out}
.kv-b{transition:background .15s,color .15s,box-shadow .2s,transform .12s}.kv-b:active{transform:translateY(1px)}
@media (prefers-reduced-motion:reduce){.kv-js .kv-hero .paint{-webkit-mask:none;mask:none}.kv-js .kv-hatch{clip-path:none}.kv-js .kv-hatch img,.kv-js .kv-hero .kv-data div,.kv-js .kv-hero .kv-acts,.kv-js .kv-steps li,.kv-js .kv-stamp{transform:none}.kv-js .kv-hero .kv-data div,.kv-js .kv-hero .kv-acts,.kv-js .kv-hero .sub,.kv-js .kv-steps li,.kv-js .kv-stamp,.kv-js .kv-stamp.in{transition:opacity .3s ease-out;transition-delay:0s!important}.kv-js .kv-stamp,.kv-js .kv-stamp.in{filter:url(#kvspray)}.kv-cell img,.kv-gal img{transition:none}}
@media (min-width:981px){.kv-hero .kv-w{padding-top:calc(80px + clamp(20px,2vw,36px))}}
@media (max-width:1100px){.kv-grid{grid-template-columns:repeat(3,1fr)}.kv-logos{grid-template-columns:repeat(4,1fr)}.kv-why{grid-template-columns:1fr 1fr}.kv-why div{padding:22px 22px 22px 0!important;border-right:0;border-bottom:1px solid #4a4f55}.kv-steps{grid-template-columns:1fr 1fr 1fr}}
@media (max-width:860px){.kv-hero .kv-w{grid-template-columns:1fr;min-height:0}.kv-hatch{aspect-ratio:12/5;max-height:none;border-width:6px}.kv-hero .sub{margin-top:.5em}.kv-hero .kv-w{padding-bottom:16px}.kv-hero h1 .l1{white-space:normal}.kv-split,.kv-svc,.kv-hist .kv-w,.kv-comp .kv-w,.kv-end .kv-w,.kv-cat-head{grid-template-columns:1fr}.kv-svc .ph{min-height:300px}.kv-steps{grid-template-columns:1fr;border-top:0}.kv-steps li{border-right:0;border-top:1px solid #4a4f55;display:grid;grid-template-columns:56px 1fr;padding:16px 0!important}.kv-steps li:before{grid-row:span 2;font-size:44px}.kv-gal{grid-template-columns:1fr 1fr}.kv-gal a:nth-child(n){grid-column:span 1;grid-row:span 1}.kv-gal a:nth-child(1){grid-column:span 2;grid-row:span 2}.kv-docs{grid-template-columns:repeat(3,1fr)}.kv-vids{grid-template-columns:1fr}.kv-news .et_pb_ajax_pagination_container{grid-template-columns:1fr 1fr}.kv-cross span{font-size:34vw}}
@media (max-width:560px){.kv-grid{grid-template-columns:1fr 1fr}.kv-logos{grid-template-columns:repeat(3,1fr)}.kv-why{grid-template-columns:1fr}.kv-docs{grid-template-columns:1fr 1fr}.kv-news .et_pb_ajax_pagination_container{grid-template-columns:1fr}.kv-b{width:100%;justify-content:center}.kv-cell h3{font-size:20px}.kv-cell .bd{padding:10px 12px 14px}}
""".replace('GUARD', GUARD).replace('NOISE', NOISE)
CSS = re.sub(r'/\*.*?\*/', '', CSS); CSS = re.sub(r'\s*\n\s*', '', CSS)

def img(src, alt, w, h, lazy=True, srcset=None, sizes=None):
    a = f'<img src="{src}" alt="{e(alt)}" width="{w}" height="{h}"'
    if srcset: a += f' srcset="{srcset}" sizes="{sizes}"'
    return a + (' loading="lazy" decoding="async">' if lazy else ' fetchpriority="high" decoding="async">')

PHONE = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M6.6 10.8a15.5 15.5 0 0 0 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.1.4 2.3.6 3.6.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1A17 17 0 0 1 3 4c0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.3.2 2.5.6 3.6.1.3 0 .7-.2 1z"/></svg>'
HERO = U + '2026/02/a52e1250-2a17-493e-9603-fd867bb0c1cd'
hero_img = img(HERO + '-768x512.webp', 'Автомобільні ваги СЦМА-КВК на об’єкті у вечірньому світлі', 768, 512, lazy=False,
               srcset=f'{HERO}-768x512.webp 768w, {HERO}-1024x683.webp 1024w, {HERO}.webp 1536w', sizes='(max-width:860px) 92vw, 40vw')

P = U + '2024/01/'
MODELS = [('Металева платформа','https://scma-kvk.com.ua/titan-standard-line-ua/','ТИТАН STANDARD LINE',P+'Установка-в-сборном-жб-фундаменте-768x432.webp'),
 ('Металева платформа','https://scma-kvk.com.ua/titan-power-line/','ТИТАН POWER LINE',P+'Установка-ВА453-в-фундамент-768x432.webp'),
 ('Металева платформа','https://scma-kvk.com.ua/titan-best-line/','ТИТАН BEST LINE',P+'Установка-А18-С80-в-сборный-фундамент-768x432.webp'),
 ('Металева платформа','https://scma-kvk.com.ua/titan-universal-line/','ТИТАН UNIVERSAL LINE',P+'Установка-желтых-в-литой-фундамет-ЭСТАКАДА-768x432.webp'),
 ('Металева платформа','https://scma-kvk.com.ua/universal_line_plus/','ТИТАН UNIVERSAL LINE PLUS+',P+'Желтые-рифленка-—-копия-768x432.webp'),
 ('Залізобетонна платформа','https://scma-kvk.com.ua/metal-concrete-line/','ТИТАН METAL CONCRETE LINE',P+'Установка-МБ18-в-фундамент-ЭСТАКАДА-768x432.webp'),
 ('Залізобетонна платформа','https://scma-kvk.com.ua/metal-concrete-line-plus/','ТИТАН METAL CONCRETE LINE PLUS+',U+'2024/02/5-1-768x483.webp'),
 ('Для залізничних вагонів','https://scma-kvk.com.ua/vagon-vesi-titan/','Вагонні ваги ТИТАН',U+'2024/12/Вагонні-Залазничні-ЖД-ваги-768x617.webp'),
 ('Для конвеєрних ліній','https://scma-kvk.com.ua/konveer-vesi-vkt/','Ваги конвеєрні типу ВКТ',U+'2022/01/vkt.webp'),
 ('Платформні ваги','https://scma-kvk.com.ua/platform-ves/','Платформні ваги типу ВПТД',P+'Коллаж-платформ-768x432.webp')]
cells = ''.join(f'<div class="kv-cell"><div class="im">{img(i, n, 768, 432)}</div><div class="bd"><h3><a href="{h}">{e(n)}</a></h3><span class="ty">{t}</span></div></div>' for t, h, n, i in MODELS)

LOGOS = [('2023/12/Arcelor_Mittal.webp','ArcelorMittal'),('2023/12/Metinvest.webp','Метінвест'),('2023/12/ugok.webp','ЮГОК'),('2023/12/INGOK.webp','ІнГЗК'),
 ('2023/12/CGZK.webp','ЦГЗК'),('2023/12/zelezorudnie.webp','залізорудне підприємство'),('2023/12/DTEK.webp','ДТЕК'),('2023/12/imgonline-com-ua-Resize-ecI9uNAM1KrM5VG.webp','Kazakhmys'),
 ('2023/12/imgonline-com-ua-Resize-YiFwoabr45InQ.webp','УКТМК'),('2023/12/imgonline-com-ua-Resize-4U7P4KAaxnt.webp','залізорудна компанія'),('2023/12/imgonline-com-ua-Resize-OVRaQXY8x9p3WU.webp','ЗЗРК'),
 ('2023/12/imgonline-com-ua-Resize-C7PYa328gSWbRJz.webp','Суха Балка'),('2024/07/2-7854.webp','Астарта-Київ'),('2024/12/2-7854-1.webp','TAS Agro')]
logos = ''.join(f'<div>{img(U+f, "Логотип "+n+" — клієнт СЦМА-КВК", 200, 120)}</div>' for f, n in LOGOS)

PH = [('IMG_7241','Автомобільна вагова станція СЦМА-КВК','1024x683'),('IMG_7113','Зважування вантажівок на автомобільних вагах СЦМА-КВК','768x512'),('IMG_7121','Автомобільна вагова платформа СЦМА-КВК','768x512'),
 ('IMG_7140','Збірка автомобільних ваг на об’єкті','768x512'),('IMG_7172','Монтаж платформи автомобільних ваг','768x512'),('IMG_7152','Фундамент для автомобільних ваг','768x512'),
 ('IMG_7202','Зварювання конструкцій автомобільних ваг','768x512'),('IMG_7195','Виготовлення металоконструкцій для автомобільних ваг','768x512'),('IMG_7218','Встановлення автомобільних ваг на підприємстві','768x512')]
gal = ''.join(f'<a href="{U}2022/01/{f}-scaled.webp">{img(U+"2022/01/"+f+"-"+sz+".webp", a, *map(int, sz.split("x")))}</a>' for f, a, sz in PH)
DOCS = [('2022/01/photo_2022-01-30_16-30-45','Дозвільний документ СЦМА-КВК'),('2022/01/photo_2022-01-30_16-30-39','Дозвільний документ СЦМА-КВК'),('2022/01/photo_2022-01-30_16-30-36','Дозвільний документ СЦМА-КВК'),
 ('2023/04/Сертифікат-автоваги-по-модулю-В-pdf','Сертифікат на автомобільні ваги по модулю B'),('2023/04/Сертифікат-вагонні-ваги-pdf','Сертифікат на вагонні ваги')]
docs = ''.join(f'<a href="{U}{f}.webp">{img(U+f+"-400x516.webp", a, 400, 516)}</a>' for f, a in DOCS)
def yt(v, t, l): return f'<button type="button" class="kv-yt" data-yt="{v}" aria-label="Відтворити відео: {e(t)}" style="background-image:url(https://i.ytimg.com/vi/{v}/hqdefault.jpg)"><i>{l}</i></button>'

FONTS = ('<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
         '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Martian+Mono:wght@400;500&family=Onest:wght@400;500;600&family=Sofia+Sans+Extra+Condensed:wght@800;900&display=swap">')
SPRAY = '<svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false"><filter id="kvspray" x="-2%" y="-2%" width="104%" height="104%"><feGaussianBlur in="SourceGraphic" stdDeviation="1.1" result="b"/><feComponentTransfer in="b" result="h"><feFuncA type="linear" slope=".45"/></feComponentTransfer><feMerge><feMergeNode in="h"/><feMergeNode in="SourceGraphic"/></feMerge></filter></svg>'

JS = r"""<script>(function(){document.documentElement.classList.add('kv-js');
var hero=document.querySelector('.kv-hero');if(hero){requestAnimationFrame(function(){requestAnimationFrame(function(){hero.classList.add('go');});});}
var els=document.querySelectorAll('.kv-steps,.kv-stamp');
if(!('IntersectionObserver' in window)){els.forEach(function(x){x.classList.add('in');});}
else{var io=new IntersectionObserver(function(es){es.forEach(function(x){if(x.isIntersecting){x.target.classList.add('in');io.unobserve(x.target);}});},{rootMargin:'0px 0px -15% 0px'});els.forEach(function(x){io.observe(x);});}
document.querySelectorAll('.kv-yt').forEach(function(b){b.addEventListener('click',function(){var f=document.createElement('iframe');f.src='https://www.youtube-nocookie.com/embed/'+b.getAttribute('data-yt')+'?autoplay=1&rel=0';f.allow='autoplay; encrypted-media; picture-in-picture';f.allowFullscreen=true;f.title=b.getAttribute('aria-label');b.appendChild(f);},{once:true});});
})();</script>"""

PART1 = f'''<script>document.documentElement.classList.add('kv-js')</script>{FONTS}<style>{CSS}</style>{SPRAY}<div class="kv">
<section class="kv-hero kv-steel"><div class="kv-w"><div>
<h1 class="kv-st"><span class="paint l1">Автомобільні ваги</span><span class="o paint" style="--d:.38s">СЦМА-КВК</span></h1>
<p class="sub">Виробництво та монтаж платформ 6–22 м, 30–100 т по Україні. Гарантуємо точність і надійність кожних ваг, які ми виготовляємо: від фундаменту до перевірки.</p>
<dl class="kv-data">
<div><dt>Довжина платформ</dt><dd>6–22 м</dd></div>
<div><dt>Вантажопідйомність</dt><dd>30–100 т</dd></div>
<div><dt>Монтаж по всій Україні</dt><dd>від 3 днів</dd></div>
<div><dt>Держкомпенсація на ваги</dt><dd><b>−25%</b></dd></div>
</dl>
<div class="kv-acts"><a class="kv-b" href="tel:+380676326798">{PHONE}Подзвонити +38 (067) 632-67-98</a><a class="kv-b ln" href="https://scma-kvk.com.ua/form/">Отримати консультацію</a></div>
</div>
<figure class="kv-hatch" style="margin:0">{hero_img}<figcaption>Платформа ТИТАН на об’єкті</figcaption></figure>
</div></section>
<div class="kv-guard" role="presentation"></div>
<div class="kv-cross" aria-hidden="true"><span>ТИТАН</span></div>
<section class="kv-sec kv-conc" id="catalog"><div class="kv-w">
<div class="kv-cat-head"><div><h2 class="kv-st">Вагове обладнання ТИТАН</h2><p class="kv-lead">Автомобільні ваги металеві та залізобетонні, вагонні, конвеєрні та платформні ваги власного виробництва.</p></div><a class="kv-b ln" href="https://scma-kvk.com.ua/equipment-ua/">Усе обладнання</a></div>
<div class="kv-grid">{cells}</div>
</div></section>
<div class="kv-guard" role="presentation"></div>
<section class="kv-sec kv-steel"><div class="kv-w">
<div class="kv-split"><div><h2 class="kv-st">Ваги завжди в наявності</h2>
<p class="kv-lead">Наші ваги завжди є в наявності і готові до відправки. СЦМА-КВК виробляє автомобільні ваги з повним циклом: від проєктування до монтажу.</p>
<p>Ми пропонуємо вагові системи для вантажних автомобілів 30–100 т, розроблені відповідно до сучасних стандартів точності та безпеки. Доставляємо ваги, встановлюємо та налаштовуємо обладнання безпосередньо на об’єкті. Наші спеціалісти швидко підберуть модель автомобільних ваг ТИТАН під особливості вашого підприємства.</p></div>
<figure class="kv-photo" style="margin:0">{img(U+"2022/01/IMG_7140-1024x683.webp","Збірка автомобільних ваг СЦМА-КВК на об’єкті",1024,683)}</figure></div>
<ol class="kv-steps"><li><b>Підбір моделі</b><span>Під завдання та особливості вашого підприємства</span></li><li><b>Проєктування</b><span>Кожен проєкт розробляємо під вашу компанію</span></li><li><b>Виробництво</b><span>Власний цех, якісна сталь, перевірені комплектуючі</span></li><li><b>Доставка і монтаж</b><span>Встановлюємо та налаштовуємо ваги на об’єкті</span></li><li><b>Перевірка і сервіс</b><span>Калібрування, діагностика та ремонт</span></li></ol>
<h2 class="kv-st" style="margin-top:clamp(72px,7vw,120px)">Якість та точність</h2>
<p class="kv-lead">Якість та точність — наша спеціалізація.</p>
<div class="kv-why">
<div><h3>Професіоналізм</h3><p>Власне виробництво, кваліфіковані конструктори та штатні монтажні бригади: ви працюватимете тільки з професіоналами.</p></div>
<div><h3>Надійність</h3><p>Тільки якісна сталь і перевірені комплектуючі. Конструкції міцні та технологічні, гарантія до 10 років.</p></div>
<div><h3>Чесна ціна</h3><p>Ціна від виробника, прозоро сформована, без посередників та дилерських націнок.</p></div>
<div><h3>Індивідуальність</h3><p>Кожен проєкт розробляємо під потреби вашої компанії. Навіть для нестандартних завдань наші інженери знайдуть рішення.</p></div>
</div></div></section>
<div class="kv-guard" role="presentation"></div>
<section class="kv-sec kv-orange kv-comp"><div class="kv-w"><div class="big kv-st kv-stamp" aria-hidden="true">−25%</div><div>
<h2 class="kv-st">Держкомпенсація на ваги «ТИТАН»</h2>
<p>SCMA-KVK є в наказі Міністерства економіки України про державну компенсацію як постачальник вагового обладнання для агропромислового комплексу. Частину вартості ваг нашого виробництва компенсує держава.</p>
<div class="kv-acts"><a class="kv-b" href="{U}2025/06/НАКАЗ-2655-з-додатком-від-17.06.25.pdf">Відкрити наказ (PDF)</a><a class="kv-b ln" href="tel:+380676326798">Уточнити умови</a></div></div></div></section>
<section class="kv-svc kv-steel"><div class="ph" role="img" aria-label="Оператор керує автомобільними вагами через сенсорний термінал" style="background-image:url({U}2022/01/IMG_7130-1024x683.webp)"></div><div class="tx">
<h2 class="kv-st">Сервісне обслуговування автомобільних, вагонних та конвеєрних ваг</h2>
<p class="kv-lead">Калібрування, діагностика та ремонт ваг, монтажні та демонтажні роботи.</p>
<div class="kv-acts"><a class="kv-b" href="https://scma-kvk.com.ua/service/">Замовити сервіс</a></div></div></section>
<div class="kv-guard" role="presentation"></div>
<section class="kv-sec kv-conc" style="padding-bottom:clamp(28px,3vw,48px)"><div class="kv-w" style="display:flex;justify-content:space-between;align-items:end;gap:24px;flex-wrap:wrap"><h2 class="kv-st" style="margin:0">Новини компанії</h2><a class="kv-ul" href="https://scma-kvk.com.ua/news-ua/">Усі новини</a></div></section>
</div>'''

PART2 = f'''<div class="kv">
<div class="kv-guard" role="presentation"></div>
<section class="kv-sec kv-steel kv-hist"><div class="kv-w">
<div class="big kv-st" aria-hidden="true">50+</div>
<div><h2 class="kv-st">Історія та досягнення</h2>
<p class="kv-lead">Вже понад 50 років наше підприємство розробляє та впроваджує складні автоматизовані системи управління для різних галузей промисловості.</p>
<p>Ми реалізуємо проєкти «під ключ», забезпечуємо сервісне обслуговування та постачання запасних частин, щоб обладнання наших клієнтів працювало стабільно й ефективно. Наші розробки відповідають міжнародним стандартам.</p>
<dl class="kv-data" style="margin-bottom:28px"><div><dt>Інноваційних розробок з патентним захистом</dt><dd>40+</dd></div><div><dt>Виробів з відзнаками українських і міжнародних виставок</dt><dd>26</dd></div></dl>
<a class="kv-b ln" href="https://scma-kvk.com.ua/about/">Детальніше про нас</a></div>
</div></section>
<div class="kv-guard" role="presentation"></div>
<section class="kv-sec kv-conc"><div class="kv-w">
<h2 class="kv-st">Наші клієнти</h2><p class="kv-lead">Гірничо-збагачувальні комбінати, металургійні, енергетичні та агропромислові підприємства.</p>
<div class="kv-logos">{logos}</div>
<blockquote class="kv-q"><p>«Від 2004 року ваги успішно експлуатуються без поломок чи збоїв у роботі. Обладнання відзначається простотою налаштування та зручністю в експлуатації. Персонал швидко навчається роботі з вагами й упевнено виконує їхнє налагодження, перевірку та технічне обслуговування.»</p><cite>Кузнєцов Є.Г., начальник ЦКБ ВАТ ЦГЗК</cite></blockquote>
</div></section>
<div class="kv-guard" role="presentation"></div>
<section class="kv-sec kv-steel"><div class="kv-w">
<h2 class="kv-st">Відео про компанію</h2>
<div class="kv-vids">{yt("WJ23Ia5s5M8","Відео про компанію СЦМА-КВК","Про компанію")}{yt("whMI_U-VeCY","Відео про виробництво автомобільних ваг СЦМА-КВК","Виробництво")}</div>
<h2 class="kv-st" style="margin-top:clamp(72px,7vw,120px)">Виробництво та об’єкти</h2>
<div class="kv-gal">{gal}</div>
</div></section>
<div class="kv-guard" role="presentation"></div>
<section class="kv-sec kv-conc"><div class="kv-w">
<h2 class="kv-st">Дозвільна документація</h2><p class="kv-lead">Сертифікати на автомобільні та вагонні ваги, дозвільні документи підприємства.</p>
<div class="kv-docs">{docs}</div></div></section>
<section class="kv-sec kv-conc kv-end" style="padding-top:0"><div class="kv-w">
<div><h2 class="kv-st">Зважений підхід гарантує успіх!</h2>
<p>Шукаєте надійні ваги для свого бізнесу? Криворізька Вагова Компанія (SCMA-KVK) пропонує широкий вибір вагового обладнання: від рішень для фермерських господарств до комплексних систем для промислових підприємств.</p>
<p>Ми виготовляємо автомобільні, платформні, залізничні та конвеєрні ваги з високою точністю, надійністю та довговічністю. Характеристики та моделі ваг є в нашому каталозі та на сайті.</p>
<div class="kv-acts"><a class="kv-b" href="{U}2024/03/Каталог-SCMA-KVK.pdf?download=1">Завантажити каталог (PDF)</a><a class="kv-b ln" href="https://scma-kvk.com.ua/equipment-ua/">Ваги на сайті</a></div></div>
<div class="side"><h3>Шукаємо партнерів та дилерів</h3>
<p style="margin-top:14px">У регіонах України для реалізації вагового обладнання та металевих платформ для автомобільних ваг. Також запрошуємо до співпраці діючі вагові компанії.</p>
<a class="kv-ul" href="https://scma-kvk.com.ua/form/">Залишити заявку на партнерство</a></div>
</div></section>
<div class="kv-guard" role="presentation"></div>
</div>{JS}'''

CONTACT = f'''<div class="kv kv-ci"><h2 class="kv-st" style="font-size:clamp(44px,5vw,88px);color:#ecebe5;margin-bottom:.3em">Залишити заявку</h2><p style="color:#b4b8bc">Залиште контакти, і наші менеджери зв’яжуться з вами найближчим часом. Або телефонуйте:</p><div class="tels"><a href="tel:+380676326798">+38 (067) 632-67-98</a><a href="tel:+380674561718">+38 (067) 456-17-18</a></div><p style="margin-top:12px"><a class="kv-ul" href="mailto:scma@ukr.net">scma@ukr.net</a></p><div class="nav"><a href="https://scma-kvk.com.ua/news-ua/">Новини</a><a href="https://scma-kvk.com.ua/equipment-ua/">Вагове обладнання</a><a href="https://scma-kvk.com.ua/service/">Сервіс</a><a href="https://scma-kvk.com.ua/about/">Про нас</a><a href="https://scma-kvk.com.ua/contacts-ua/">Контакти</a></div></div>'''

def one(s): return re.sub(r'\s*\n\s*', '', s)
for n, s in (('P1', PART1), ('P2', PART2), ('C', CONTACT)):
    assert '[' not in s and ']' not in s, n
form = open(f'{SCR}/home/contact_form.txt').read()
BV = '_builder_version="4.27.2" _module_preset="default"'
content = (
 f'[et_pb_section fb_built="1" fullwidth="on" {BV} custom_padding="0px||0px||true|false"][et_pb_fullwidth_code {BV}]{one(PART1)}[/et_pb_fullwidth_code][/et_pb_section]'
 f'[et_pb_section fb_built="1" {BV} module_class="kv-news-sec" custom_padding="0px||136px||false|false"][et_pb_row {BV} width="100%" max_width="1360px"][et_pb_column type="4_4" {BV}]'
 f'[et_pb_blog fullwidth="on" posts_number="3" show_author="off" show_categories="off" show_comments="off" show_pagination="off" show_more="off" header_level="h3" excerpt_length="120" module_class="kv-news" {BV}][/et_pb_blog]'
 f'[/et_pb_column][/et_pb_row][/et_pb_section]'
 f'[et_pb_section fb_built="1" fullwidth="on" {BV} custom_padding="0px||0px||true|false"][et_pb_fullwidth_code {BV}]{one(PART2)}[/et_pb_fullwidth_code][/et_pb_section]'
 f'[et_pb_section fb_built="1" {BV} module_class="kv-contact-sec"][et_pb_row column_structure="1_2,1_2" {BV} width="100%" max_width="1360px"]'
 f'[et_pb_column type="1_2" {BV}][et_pb_code {BV}]{one(CONTACT)}[/et_pb_code][/et_pb_column]'
 f'[et_pb_column type="1_2" {BV}]{form}[/et_pb_column][/et_pb_row][/et_pb_section]')
open(OUT, 'w').write(content); print('content length', len(content))

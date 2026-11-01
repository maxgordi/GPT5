#!/usr/bin/env python3
"""Одной командой закрыть все фоновые приложения на LG TV, кроме YouTube и ТВ.

  python cleanup.py        # без вопросов (ничего не удаляет, только закрывает)
Перед первым запуском: python lgtv.py find && python lgtv.py pair
"""
import asyncio

from lgtv import cmd_close

if __name__ == "__main__":
    asyncio.run(cmd_close(confirm=False))

# BGA HackNSlash Notes

This folder is the BoardGameArena implementation scaffold.

- Keep BGA entry file names in lowercase `hacknslash.*`.
- Keep rules minimal until they are explicitly specified.
- Put reusable server logic in `modules/HNS_*.php` traits.
- Put generated/static game material in `modules/material/*.inc.php`.
- Put BGA-deployed assets under `img/` only.
- Use TDD by default: add or update the failing test first, then implement the minimal code change, then run the relevant test suite.

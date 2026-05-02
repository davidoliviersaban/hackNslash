# HackNSlash BGA Scaffold

This is the initial BoardGameArena structure for HackNSlash.

It provides:
- BGA entry files: PHP game/action/view, JS, CSS, template.
- Database tables for cards, dungeon tiles, entities, and global variables.
- A minimal state machine: player turn, action resolution, next player, game end.
- Modular PHP traits inspired by the Windwalkers BGA implementation.
- Placeholder material files to be replaced or generated from `src/resources`.
- Asset folders ready for BGA deployment.

Rules are intentionally not implemented yet. The next step is to define the exact online game flow, then fill the state machine and material generators.

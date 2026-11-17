# Sub-agents: model and effort

Pick the cheapest model and the lowest effort that does the task well, judged by the cost of finishing it reliably, retries included. A user or project instruction about models or effort always wins over this guideline.

- Name the model and effort on every sub-agent you spawn, and state the choice and the reason in one line, such as "Spawning shipkit-explorer on haiku, medium effort: a file search."
- Prefer ShipKit's agent definitions, which pin both in their frontmatter: `shipkit-standards-reviewer` and `shipkit-spec-reviewer` (opus, low) for `review-diff`, and `shipkit-explorer` (haiku, medium) for searches. `php artisan shipkit:agents` installs them into `.claude/agents`; a project changes one in `config/shipkit.php` under `agents`.
- Where a client has no agent definitions, pass the model and effort explicitly from the table.

| Work                                                     | Model  | Effort          |
| -------------------------------------------------------- | ------ | --------------- |
| Searches, lookups, summaries, logs, mechanical edits     | haiku  | medium to xhigh |
| Routine implementation, refactors, tests                 | sonnet | low to high     |
| Code review, tricky debugging, planning                  | opus   | low to medium   |
| The most ambitious technical or research work, sparingly | fable  | as needed       |

- Never pass `max`.
- Go lower in a range for lookups and mechanical changes, higher when the work needs care.
- When a sub-agent fails, first check whether it lacked context or a way to verify its work. Then raise effort one step; at the top of a model's range, move to the next model up.

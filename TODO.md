Phase 1a: Refine the Interwiki System
- Implement section interwikis correctly - also rethink order + section
  - Normalize section values: spaces -> _ (basically, valid HTML anchors?)
- Handle conflicts (multiple pages on the same wiki/translation project with the same key in the same order)
  - example in dev environment: hermes_tag `golem` in Esperanto could resolve to either `Golemo` or `Fergolemo` on `en:Golem` (arbitrarily resolves correctly currently).
  - Should maybe throw error or add to maintenance category
- Is there a proper way to define extension maintenance categories? If so, would need to adapt #hermes duplicate category, if so.

(Cleanup Phase: Refactor tests, and make them more readable)

Phase 1b: Refine current state of translation projects
- Fix interwiki links to translation projects, e.g. `[[:es:Golem]]` currently links to the English page.
  - The current hijacking of interwiki links should also use `parseTitle` for PageInfos from `fromRow`.
- If `$wgCapitalLinks` is true, Hermes should also force the title after the project prefix to start with a capital letter.

Phase 2: Add features to translation projects
- Project-internal links: e.g. on `!xx:` translation project pages, `[[foo]]` should turn into `[[!xx:foo]]` and `[[!:bar]]` should turn into `[[bar]]`
  - With namespaces: `[[Namespace:Foo]]` -> `[[Namespace:!xx:Foo]]`, `[[Namespace:!:Foo]]` -> `[[Namespace:Foo]]`
- Links should link to the main article if the linked page exists in the translation project, but does outside (and offer a link to translate)
- Similar for templates, though `{{foo}}` should always resolve to `[[Template:Foo]]` if `[[Template:!xx:Foo]]` does not exist
- Also categories: in project `!xx:`, `[[Category:Foo]]` should become `[[Category:!xx:Foo]]`
- Proper link display in categories (might be solved together with the two points above)
  - Also proper ordering (sort key) within categories (without overwriting custom sortkeys in `[[Category:Foo|sotrkey]]`)
- Restrict search to current language / translation project
- Make logo (+ sidebar) link to pages in the project language, if they exist

Phase 3: User Interface
- Special pages for managing tags and languages

Phase 4: Improved Caching
- Currently caching doesn't cause issues in my dev environment
- In prod, this _will_ be an issue
- I'd like to add an extra table that adds all potentially affected pages on all wikis whenever a hermes tag changes
- This table is then checked by a wiki whenever its cache update task runs (not sure how that works exactly, might need to ask our wiki hosting people)

Phase 5: Lua + Javascript interop
- Not sure if needed but would be nice.

Phase 6: Shoot for the Moon - more syncing!
- I'm wondering if adding some "git-like" sync of some data pages across wikis (without needing a bot) would be possible at all.
- E.g. a namespace "Data:" or "Git:"
- Again, issue will be caching.
- Would suffice if Scribunto can interface with this.

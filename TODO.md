Phase 1: DONE

Phase 2: Skipped

Phase 3: Links in translation projects
- Project-internal links: e.g. on `!xx:` translation project pages, `[[foo]]` should turn into `[[!xx:foo]]` and `[[!:bar]]` should turn into `[[bar]]`
  - With namespaces: `[[Namespace:Foo]]` -> `[[Namespace:!xx:Foo]]`, `[[Namespace:!:Foo]]` -> `[[Namespace:Foo]]`
- Links should link to the main article if the linked page exists in the translation project, but does outside (and offer a link to translate)
- Similar for templates, though `{{foo}}` should always resolve to `[[Template:Foo]]` if `[[Template:!xx:Foo]]` does not exist
- Also categories: in project `!xx:`, `[[Category:Foo]]` should become `[[Category:!xx:Foo]]`
- In translation projects, allow using namespace from the language of the translation project in addition to the wiki's base language. E.g. for Esperanto, `[[Kategorio:Foo]]` should resolve to `[[Category:!eo:Foo]]`.
- Proper link display in categories (might be solved together with the two points above)
  - Also proper ordering (sort key) within categories (without overwriting custom sortkeys in `[[Category:Foo|sotrkey]]`)

Phase 4: User Interface
- Special pages for managing tags and languages, creating a translated page
- Maybe something like {{#hermes-link:tag|lang=es}} / {{#hermes-query:}} for generating hermes links to an article with a specific tag?
- Side quest: Rename "order" to something like "fallback priority" everywhere

Phase 5: Improved Cache Handling
- Currently caching doesn't cause issues in my dev environment
- In prod, this _will_ be an issue
- I'd like to add an extra table that adds all potentially affected pages on all wikis whenever a hermes tag changes
- This table is then checked by a wiki whenever its cache update task runs (not sure how that works exactly, might need to ask our wiki hosting people)

Phase 6: Keep languages isolated
- Restrict search by language with `hermesproject:`
- Make logo (+ sidebar) link to pages in the project language, if they exist

Phase 7: Lua + Javascript interop
- Not sure if needed but would be nice.
- Automatically fill `hermesproject:` in search when searching in a particular project

Phase 8: Namespaces
- For example: `{{#hermes:creeper}}` page in the main namespace should link to `{{#hermes:dungeons:creeper}}` page in the "Dungeons" namespace
- this means that a hermes tag can have at most one namespace name before ":" (can be arbitrary)
- This can be added to the Vector UI (e.g. as tabs)

Phase 9: Shoot for the Moon - more syncing!
- I'm wondering if adding some "git-like" sync of some data pages across wikis (without needing a bot) would be possible at all.
- E.g. a namespace "Data:" or "Git:"
- Again, issue will be caching.
- Would suffice if Scribunto can interface with this.

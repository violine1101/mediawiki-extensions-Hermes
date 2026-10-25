Phase 1: DONE

Phase 2: Skipped

Phase 3: Links in translation projects - DONE

Phase 4: User Interface - DONE

Phase 4.1: Fix language conflicts
- When a language exists both as a translation project and as a wiki, the extension currently breaks
- It'd be great if the extension had built-in support for migrating a translation project to a new wiki (and subsequently wouldn't break if a new wiki has the same language as a translation project)
- Maybe support for exporting all pages of a translation project?
- Special:HermesProjects: add support for removing translation projects in Special:HermesProjects, auto-deleting all pages. Only works with mass delete rights
- On Hermes special pages, show all pages, nut just those tagged with `{{#hermes:}}`

Phase 5: Improved Cache Handling
- Currently caching doesn't cause issues in my dev environment
- In prod, this _will_ be an issue
- I'd like to add an extra table that adds all potentially affected pages on all wikis whenever a hermes tag changes
- This table is then checked by a wiki whenever its cache update task runs (not sure how that works exactly, might need to ask our wiki hosting people)

Phase 6: Keep languages isolated
- Restrict search by language with `hermesproject:`
- Make logo (+ sidebar) link to pages in the project language, if they exist
- Add filters to recent changes

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

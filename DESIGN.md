## Babel: Design document
_NB: This is the original design document. The extension has since been renamed to "Hermes", and some design decisions have changed._

I've wanted to do a larger and more organized write-up about this idea for a while now, but until I get there I figured I'd just quickly write down the ideas I have so they don't get lost.

I call this project **[Babel](https://en.wikipedia.org/wiki/Tower_of_Babel)**. (I'm aware that [Extension:Babel](https://www.mediawiki.org/wiki/Extension:Babel) exists, I'm open to other name suggestions.) It is meant to do the following things:
1. Replace the interwiki link system entirely
2. Make translation projects more approachable
3. Make translation projects discoverable through interwiki links

Implementing this would most likely (aka definitely) require a custom extension. I haven't implemented (or tried to implement) anything yet; this has just been an idea I've had and I'm not 100% sure how feasible it actually is. However I'm rather confident that it would be possible to implement ourselves, just not sure how difficult.

[Technical detail: for (1) and therefore also (3), we'll need a database table that's shared across all wikis. There may be difficulties with getting caching / page purges to work right.]

## Replacing the interwiki system 
The idea is as follows; instead of having the following at the bottom of a page:
```
== Navigation ==
{{Navbox iron}}
{{Navbox mobs}}

[[Category:Golem mobs]]
[[Category:Neutral mobs]]

[[de:Eisengolem]]
[[es:Gólem de hierro]]
[[fr:Golem de fer]]
[[hu:Vasgólem]]
[[it:Golem di ferro]]
[[ja:アイアンゴーレム]]
[[ko:철 골렘]]
[[nl:IJzergolem]]
[[pl:Żelazny golem]]
[[pt:Golem de ferro]]
[[ru:Железный голем]]
[[uk:Залізний ґолем]]
[[zh:铁傀儡]]
```

It'd look like this:
```
== Navigation ==
{{Navbox iron}}
{{Navbox mobs}}

[[Category:Golem mobs]]
[[Category:Neutral mobs]]

{{#babel:iron_golem}}
```

This would classify the page with the `iron_golem` tag and add interwiki (or technically, interlanguage) links for all pages on all other wikis that also have that tag. E.g. on the French wiki, it'd also have this `{{#babel:iron_golem}}` at the bottom of the page, and therefore link back to the English page "Iron Golem" with that same tag.

This way, the only thing that editors need to worry about is to add the correct tag to the bottom of the page, and boom, all interwiki links are always automatically updated!

### Conflicts
It's possible that there are two pages on the same wiki with the same tag. In that case, the extension should probably add both pages to a maintenance category, and/or list them on a special page. Some arbitrary rule is used to determine to which page other wikis link (e.g. which page is older, or which page has more bytes currently).

### Special pages
There could be a special page that lists all interwiki tags across all wikis (since the database table would be shared), or a special page for which pages have which interwiki tag, or which interwiki tags aren't set at all on a wiki (useful to find which pages haven't been translated yet).

### N:M relationships
Not all articles have a 1:1 relationship across wikis. Babel could account for that by setting multiple tags for a page. For example, consider the page for "shovel" on wiki X (which has a page for all shovels, but no pages for individual shovels):
```
{{#babel: shovel; wooden_shovel; gold_shovel; copper_shovel; iron_shovel; diamond_shovel; netherite_shovel}}
```
It adds the interwiki links for the tag `shovel` first; then, for those languages that don't have a link yet, the interwiki links for `wooden_shovel`, then `gold_shovel`, and so on. Usually, all wikis should have a page for `shovel` though, as it's the collection article, although it's also possible to imagine cases where that's not the case. This list of tags acts as a "fallback chain" for finding a fitting interwiki link.

On a wiki Y where each shovel has a separate page, each of those pages would just have that single tag. For example, the overview page for all shovels has `{{#babel:shovel}}`, and the page for wood shovels has `{{#babel:wooden_shovel}}`. Both link to the overview article on wiki X.

#### Sections
Sometimes, interwiki links point to sections. In this case, I could imagine a syntax like the following (example is [en:Ender Dragon#Dragon Fireball](https://minecraft.wiki/w/Ender_Dragon#Dragon_Fireball)):
```
{{#babel:ender_dragon|#Dragon Fireball=ender_dragon_fireball}}
```
If another wiki has a separate page for the dragon fireball, using `{{#babel:ender_dragon_fireball}}` there would then automagically add an interwiki link to `Ender Dragon#Dragon Fireball`, since that has the matching tag.

### Organized tags
It'd probably be a good idea to organize interwiki tags with some kind of prefix, but I suppose that'd be up to the users of the extension. E.g. the MCW should probably do something like `item/shovel`, `entity/iron_golem`, `tutorial/build_house` or `dungeons/entity:pillager`.

## More approachable translation projects
Inspired by the translation system here on meta, this extension could add a new `Translation:` namespace for configured wikis (likely just EN and Meta) where each page title has the following page structure:
```
Translation:EO/Ŝtono
```
"Translation:" is namespace, "EO" is the language code (in this case Esperanto, as an example), and "Ŝtono" is the Esperanto word for "Stone".

Since the extension knows which language this page is in, it could automatically display a notice in that language that this is a translation project. And maybe also change the interface language to that language! (and of course the page language too, looking awkwardly at LanguageConverter)

The page "Translation:EO" (or similar) could be an overview page for the project that the project members can set up as they wish (i.e. it would be the same thing as / a replacement of [[en:MCW:Projects/Esperanto translation]]).

### Replacing links
Now, here's the part that's interesting: Here, on the meta wiki, we have the template [Template:Trl](https://meta.minecraft.wiki/w/Template:Trl), which links to the translated page if available, and if not, links to the original page with a <sup>[translate]</sup> link.

The extension could implement exactly that for the vanilla link syntax! (see also [InternalParseBeforeLinks](https://www.mediawiki.org/wiki/Manual:Hooks/InternalParseBeforeLinks) hook and [WikimediaIncubator](https://www.mediawiki.org/wiki/Extension:WikimediaIncubator))

Examples:
```
[[Ŝtono]]
-> link exists in EO, so turn to [[Translation:EO/Ŝtono|Ŝtono]] in the backend

[[Glata ŝtono]] (smooth stone)
-> link doesn't exist in EO, and doesn't exist in EN, so turn into redlink [[Translation:EO/Glata ŝtono|Glata ŝtono]]

[[Purpur block]]
-> link does not exist in EO, so turn to [[Purpur block]]<sup>[[Special:BabelTranslate/EO/Purpur block|translate]]</sup> or something similar

[[Cobblestone]]
-> link does not exist in EO.
However, the EN page for "Cobblestone" has an interwiki link to "Translation:EO/Pavimŝtono" (will get to that later)
-> so, turn to [[Cobblestone]]<sup>[[Translation:EO/Pravimŝtono|translation]]</sup> or similar
```

Notes about that last example:

Basically, pages would be grouped by the `{{#babel:cobblestone}}` tag. So, the pages "en:Cobblestone", "de:Bruchstein", "es:Roca", "en:Translation:EO/Pavimŝtono" etc. would all be in a "cross-wiki category". So when `[[Cobblestone]]` is used in EO translation project, it's checking:
1. does `en:Translation:EO/Cobblestone` exist? -> if yes, link to that, otherwise:
2. does `en:Cobblestone` exist? -> if no, redlink to `en:Translation:EO/Cobblestone`, otherwise:
3. does `en:Cobblestone` have an entry in the `{{#babel:}}` group for the `EO` language? -> if yes, link to that as `[[Translation:EO/<that page>|Cobblestone]]`
4. otherwise, add redlink `[[en:Cobblestone|Cobblestone]] <sup>(translate)</sup>`

### Replacing templates
I'd imagine this would probably work just as well for templates:
```
{{infobox block|parameter=something}}
-> {{Translation:EO/Template:Infobox block|parameter=something}}
    or {{Template:Translation:EO/Infobox block|parameter=something}}, not sure which one would be better
    if that translated template exists
-> {{Template:Infobox block|parameter=something}} otherwise
```

### Categories
Categories could use this system as well, e.g.:
```
[[Category:Something]] -> [[Category:Translation:EO/Something]]
```

### Search
If on a translation page, the search bar would filter for pages in the translation project. Outside of the translation project, search results would be shown in the sidebar, similar to other wikis. (this btw could maybe also be implemented directly through the extension, rather than our current workaround with JS)

## Bringing it together: interwikis for translation projects
And of course, pages on translation projects could use the `{{#babel: ...}}` syntax as well. Since they're in the translation namespace under a specific language code, the extension would know which language the page belongs to, and add a separate entry (with a separate language identifier for the translation project language) to the shared interwiki database table. The interwiki link would then show up on all related pages on all wikis (and also on the same wiki as the translation project, and also all other translation projects)! Yay!

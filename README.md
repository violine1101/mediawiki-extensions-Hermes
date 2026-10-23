# Hermes MediaWiki extension

🚧 **WORK IN PROGRESS – DO NOT USE IN PRODUCTION** 🚧

This is a lightweight and simple extension that automates interlanguage links (the "In other
languages" box) across a family of wikis and adds support for translation projects.

Unlike [Extension:Translate](https://www.mediawiki.org/wiki/Extension:Translate), this extension
does not assume that translations match with the original exactly and does not include any in-wiki
translation features.

Similarly, while Wikipedia uses [Wikibase](https://www.mediawiki.org/wiki/Wikibase) for automating
interlanguage links, using Wikibase is overkill for smaller wiki families.

## Design document
The original design document for this extension can be found at `DESIGN.md`.

## Name
In Ancient Greek mythology, [Hermes](https://en.wikipedia.org/wiki/Hermes) is the god of travelers,
language, and messaging (among others). This extension aims to bridge different languages and
features messaging between different wikis using shared data.

## AI disclosure
This extension is currently still in the prototyping phase. I'm using Claude for code generation while I flesh out the features of this extension. After code has been generated, I verify that it makes sense and works as it should. However, most tests are fully auto-generated and not verified.

Before the extension leaves the prototyping phase, all code will again be manually reviewed, rewritten, and/or refactored by me to ensure that the code is high quality and does in fact do what it should.

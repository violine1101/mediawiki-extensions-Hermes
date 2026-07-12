# Hermes MediaWiki extension

This is a lightweight and simple extension that automates the MediaWiki interwiki system and adds
support for translation projects.

Unlike [Extension:Translate](https://www.mediawiki.org/wiki/Extension:Translate), this extension
does not assume that translations match with the original exactly and does not include any in-wiki
translation features.

Similarly, while Wikipedia uses [Wikibase](https://www.mediawiki.org/wiki/Wikibase) for automating
interwikis, using Wikibase is overkill for smaller wiki families.

## Design document
The original design document for this extension can be found at `DESIGN.md`.

## Name
In Ancient Greek mythology, [Hermes](https://en.wikipedia.org/wiki/Hermes) is the god of travelers,
language, and messaging (among others). This extension aims to bridge different languages and
features messaging between different wikis using shared data.

## AI disclosure
This extension is currently still in the prototyping phase. I'm using Claude for automating some tasks; in particular, most tests are fully auto-generated.

For the rest of the codebase: Some parts I've written myself, and those that Claude generated I've reviewed, rewritten, and/or refactored to ensure that the code is high quality and does in fact do what it should.

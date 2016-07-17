# Base16 Builder PHP
An example PHP implementation of a base16 builder that follows the conventions described at https://github.com/chriskempson/base16.
Requires PHP 5.3 or greater.

**This is an early release and is likely to change.** However, it is already being used to generate the themes for [Base16 Vim](https://github.com/chriskempson/base16-vim), [Base16 Textmate](https://github.com/chriskempson/base16-textmate), [Base16 Shell](https://github.com/chriskempson/base16-shell) and [Base16 HTML Previews](https://github.com/chriskempson/base16-html-previews).

## Installation

    git clone https://github.com/chriskempson/base16-builder-php
    cd base16-builder-php
    composer install

## Usage

    php Builder.php update
Updates all scheme and template repositories as defined in `schemes.yaml` and `templates.yaml`.

    php Builder.php
Build all templates using all schemes

    php Builder.php -p path/to/profile.terminal
Same as above, but uses a Terminal.app profile as template.

## Why PHP?
It's easy to read PHP and this Base16 Builder is not meant to be the defacto CLI builder app but serves as a simple working reference application from which others can build Base16 Builders in languages better suited to their needs.

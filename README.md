# phpBB SEO URLs

This is an extension for the phpBB forums software. You need an instalation of <a href="https://github.com/phpbb/phpbb">phpBB 3.1.x</a> to use this extension.

## DESCRIPTION
Changes the URLs of forums and topics too more SEO friendly URLs with the title of the forums and topics in it. This
extension is held as simple as possible, it contains no ACP, you can just install it and use the SEO friendly URLs.

* `/viewforum.php?f=1 becomes /forum-title-f1/`
* `/viewtopic.php?f=1&t=2 becomes /topic-title-t2.html`


## INSTALLATION
To install this extension download it from here and upload the files in your forum under <b>/ext/tas2580/seourls</b>.
After that go to the Admin panel of your forum and navigate in to Customise -> Extension Management -> Extensions. Search this extension in the list of extensions and click on Enable.

### URL Rewriting
The extension modifies the links that are outputed by the forum. So you need to rewrite the new links to working URLs.

#### Apache
Open your .htaccess and find <code>RewriteEngine on</code> right after this add the following code:
```
RewriteBase /

RewriteRule ^(.*)-f([0-9]*)-([0-9]*)\.html viewforum.php?f=$2&start=$3&%{QUERY_STRING} [L]
RewriteRule ^(.*)-f([0-9]*)\.html viewforum.php?f=$2&%{QUERY_STRING} [L]
RewriteRule ^(.*)-t([0-9]*)-([0-9]*)\.html viewtopic.php?t=$2&start=$3&%{QUERY_STRING} [L]
RewriteRule ^(.*)-t([0-9]*)\.html viewtopic.php?t=$2&%{QUERY_STRING} [L]

```
If your forum is under domain.tld/forum you also need to change <code>RewriteBase /</code> to <code>RewriteBase /forum</code>

## SUPPORT
You can get support for the original extension on <a href="https://www.phpbb.com/community/viewtopic.php?f=456&t=2288486">phpbb.com</a>
or in german on <a href="https://www.phpbb.de/community/viewtopic.php?f=149&t=233380">phpbb.de</a>. For more informations look at
<a href="https://tas2580.net/downloads/phpbb-seo-url/">the author Website</a>.

## LICENSE
<a href="http://opensource.org/licenses/gpl-2.0.php">GNU General Public License v2</a>


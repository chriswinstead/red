[b]Installing On Debian[/b]

While following the instructions for any other installation will work on Debian, for this platform we also provide an install script 
which can be [url=http://gitweb.whogotzot.com/debian-install-script]downloaded here[/url]

[b]THIS SCRIPT IS MEANT TO BE RUN ON A NEW OR JUST REINSTALLED SERVER[/b]

Some programs such as Apache &amp; Samba are removed by this script.

Note, this script will use Nginx as the webserver.  It will also install PHP and MySQL from the DotDeb repository.  The DotDeb is not an official Debian repository, though it is maintained by Debian developers.

The file setup-debian.sh has to be on your server.

For the initial setup git may not be installed on your server, to install git:

[code]apt-get install git[/code]

If wget is installed try

[code]wget http://git.beardyunixer.com/debian-install-script/blob/HEAD:/debian-setup.sh[/code]

To install wget:
[code]apt-get install wget[/code]

For intitial server setup run
[code]bash setup-debian.sh all[/code]

To install Red for domain example.com, after the initial server setup run

[code]bash setup-debian.sh red example.com[/code]

#include doc/macros/main_footer.bb;

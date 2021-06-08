# OIHAquadocsParser
get the information from the Aquadocs repository OAI interface and make it available for OIH in JSON-LD format

This script is installed on the Wordpress server (mainly because the OIH site is installed there).
The main reason for this is that we cannot change the HTML pages on the DSpace Server that is managed by Atmire.
For this to work the ODIS-Arch has been adopted to accept graphs (https://book.oceaninfohub.org/appendix/graphpub.html)

Can be found in /var/www/html/oih.aquadocs.org on the wordpress.iode.vliz.be server.

The resulting graph can be found/should be available, via https://oih.aquadocs.org/aquadocs.json.

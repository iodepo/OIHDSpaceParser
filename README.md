# OIHDSpaceParser
get the information from a DSpace repository OAI interface and make it available for OIH in JSON-LD format

The main reason for this is that we cannot change the HTML pages on the DSpace Server for Aquadocs that is managed by Atmire.
For this to work the ODIS-Arch has been adopted to accept graphs (https://book.oceaninfohub.org/appendix/graphpub.html)

The DSpace OAI interface/api should give you a file like DSpaceOutputExample.xml (should be in this directory).

## installation
this script should be installed on a server (typically in ***/usr/local/bin***) that has write access to the place where we will serve the resulting json file.
The installation requires PHP >= 5.6 to be installed (typically in /usr/bin/, if not change the first line of the parser.php file).
```
cd /usr/local/bin
git clone git@github.com:iodepo/OIHDSpaceParser.git
cd /usr/local/bin/OIHDSpaceParser
git checkout tags/0.1.0
```

Make the script executable
```
sudo chmod +x /usr/local/bin/OIHDSpaceParser/parser.php
```

At **IODE**, this script is installed on the Wordpress server (mainly because the OIH site is installed there).
The resulting file will be written to /var/www/html/oih.aquadocs.org/aquadocs.json and will be accessible via https://oih.aquadocs.org/aquadocs.json.

Don't forget to give this information in the ***Startpoint URL for ODIS-Arch*** field in [ODISCat](https://catalogue.odis.org).
The ***Type of the ODIS-Arch URL*** should be set to ***sitegraph***

## usage
### run manually
```
/usr/local/bin/OIHDSpaceParser/parser.php --verbose --url=https://aquadocs.org --metadataPrefix=oai_dc --output=/var/www/html/oih.aquadocs.org/aquadocs.json
```

For [OBPS](https://repository.oceanbestpractices.org) this would be
```
./parser.php --url=https://repository.oceanbestpractices.org --metadataPrefix=oai_dc --output=/tmp/test.txt --verbose
```

For [Aquadocs](https://aquadocs.org) this would be
```
./parser.php --url=https://aquadocs.org --metadataPrefix=oai_dc --output=/tmp/test.txt --verbose
```

### cron
typically this script will be run in a cronjob
- open crontab as root
```
crontab -e
```
- add
```
#uncomment to run the script every 4 hours
#0 */4 * * * /usr/local/bin/OIHDSpaceParser/parser.php -q --url=https://aquadocs.org --metadataPrefix=oai_dc --output=/var/www/html/oih.aquadocs.org/aquadocs.json 2>&1

#uncomment to run the script every hour
#0 * * * * /usr/local/bin/OIHDSpaceParser/parser.php -q --url=https://aquadocs.org --metadataPrefix=oai_dc --output=/var/www/html/oih.aquadocs.org/aquadocs.json 2>&1

#uncomment to run the script once every day (at midnight) 
#0 0 * * * /usr/local/bin/OIHDSpaceParser/parser.php -q --url=https://aquadocs.org --metadataPrefix=oai_dc --output=/var/www/html/oih.aquadocs.org/aquadocs.json 2>&1
```

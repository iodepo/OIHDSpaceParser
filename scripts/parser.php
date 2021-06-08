#! /usr/bin/php
<?php
/**
 * try to read the OAI interface output from the DSpace for Aquadocs
 * and transform the DublinCore stuff into a JSON-LD as defined by the ODIS-Arch project
 *
 * @author Arno Lambert <a.lambert@unesco.org>
 * @since 24/02/2021
 *
 */

include_once('config.inc.php');
$nextRepoUrl = $repoURL;

//read the content of the page
$dom = new domDocument();
$lastPage = false;
$outputJSON = array();

while ($nextRepoUrl != '') {
    print "\n*************************\n";
    print "$nextRepoUrl \n";

    /*
     * get the complete page in one long string
     * replace the dublin core tags with something without prefixes
     */
    try {
        $page = file_get_contents($nextRepoUrl);

        $patterns = array(
            '/<oai_dc:dc\s/',
            '/<dc:/',
            '/<\/oai_dc:dc/',
            '/<\/dc:/',
        );
        $replace = array(
            '<oai_dc_dc ',
            '<dc_',
            '</oai_dc_dc',
            '</dc_',
        );
        $page = preg_replace($patterns, $replace, $page);

        //file_put_contents('output.xml', $page);
        $oai = new SimpleXMLElement($page);

        print $oai->responseDate;

        foreach ($oai->ListRecords->record as $record) {
            //print "\n*************************\n";
            //print $record->header->identifier . ' ' . $record->header->datestamp . "\n";

            //subtract the handle id from the identifier
            $identifier = $record->header->identifier;
            $idSuffix = preg_replace(
                '/.+?:(\d+\/\d+)/',
                "$1",
                $identifier
            );

            //to what (sub)sets belongs this document
            foreach ($record->header->setSpec as $set) {
                //print $set . "\n";
            }

            //read all the metadata and put the values in vars ready for output

            //we often use the same regex
            $pattern = array(
                "\n",
                "\r",
                "\t"
            );
            $replace = array(
                ' ',
                ' ',
                ' '
            );
            $metaData = $record->metadata->oai_dc_dc;
            $name = trim($metaData->dc_title);
            $name = str_replace(
                $pattern,
                $replace,
                $name
            );

            //get all the descriptions
            //put them in one long description
            $descriptions = array();
            $descriptionString = '';
            foreach ($metaData->dc_description as $description) {
                $description = str_replace(
                    $pattern,
                    $replace,
                    trim($description)
                );
                $descriptions[] = ' - ' . $description . ' - ';
            }
            $descriptionString = implode(', ', $descriptions);

            //this is the link to the document
            $url = 'https://www.oceandocs.org/handle/' . $idSuffix;

            //get all the creators
            $creators = array();
            foreach ($metaData->dc_creator as $creator) {
                $creator = str_replace(
                    $pattern,
                    $replace,
                    trim($creator)
                );
                $creators[] = $creator;
            }

            //get all the keywords
            $keywords = array();
            $keywordString = '';
            foreach ($metaData->dc_subject as $keyword) {
                $keyword = str_replace(
                    $pattern,
                    $replace,
                    trim($keyword)
                );
                $keywords[] = $keyword;
            }
            $keyword = implode(', ', $keywords);

            //special case for contributor
            $publisher = '';
            if (isset($metaData->dc_publisher)) {
                $publisher = $metaData->dc_publisher;
                $publisher = str_replace(
                    $pattern,
                    $replace,
                    trim($publisher)
                );
            }

            $JSON = array(
                '@context' => array(
                    '@vocab' =>  'https://schema.org/'
                ),
                '@type' => 'CreativeWork',
                '@id' => "$identifier",
                'name' => "$name",
                'description' => "$descriptionString",
                'url' =>  "$url",
                'identifier' => array(
                    '@id' => "https://hdl.handle.net/$idSuffix",
                    '@type' => 'PropertyValue',
                    'propertyID' => 'https://hdl.handle.net/',
                    'value' => "$idSuffix",
                    'url' => "https://hdl.handle.net/$idSuffix"
                )
            );

            /*
             * authors/creators
             * ODIS Arch expects
             * 'author': {
             *      '@id': 'https://www.sample-data-repository.org/person/51317',
             *      '@type': 'Person',
             *      'name': 'Dr Uta Passow',
             *      'givenName': 'Uta',
             *      'familyName': 'Passow',
             *      'url': 'https://www.sample-data-repository.org/person/51317'
             * },
             */
            $authors = array();
            if (isset($creators)
                && count($creators)
            ) {
                foreach ($creators as $creator) {
                    array_push(
                $authors,
                         array(
                            '@type' => 'Person',
                            'name' => "$creator"
                        )
                    );
                }
            }

            if (count($authors) ) {
                $JSON['author'] = $authors;
            }

            $contributors = array();
            //in OIH/ODIS Arch, publishers are seen as contributors
            if (isset($publisher)
                && $publisher != ''
            ) {
                array_push(
                    $contributors,
                     array(
                        '@type' => 'Organization',
                        'name' => "$publisher"
                    )
                );
            }

            if (count($contributors) ) {
                $JSON['contributor'] = $contributors;
            }

            if (isset($keywords)
                && count($keywords)
            ) {
                $JSON['keywords'] = $keywords;
            }
            $outputJSON[] = array(
                '@type' => 'ListItem',
                'item' => $JSON
            );
        }

        //make the url for the next page if there is any
        $nextRepoUrl = '';
        if (isset($oai->ListRecords->resumptionToken)
            && $oai->ListRecords->resumptionToken != ''
        ) {
            $nextRepoUrl = str_replace(
                'metadataPrefix=oai_dc',
                'resumptionToken=' . $oai->ListRecords->resumptionToken,
                $repoURL
            );
        }
    } catch (Exception $e) {
        print 'there seems to be a problemo....' . $e->getMessage() . "\n";
        exit(1);
    }
}

$outputGraph = array(
    '@context' => array(
        '@vocab' =>  'https://schema.org/'
    ),
    '@type' => array(
        'ItemList',
        'CreativeWork'
    ),
    'name' => 'Resource collection for AquaDocs.org',
    'author' => 'Arno Lambert <a.lambert@unesco.org>',
    'itemListOrder' => 'https://schema.org/ItemListUnordered',
    'numberOfItems' =>  count($outputJSON),
    'itemListElement' => $outputJSON
);
//finally save the file to be accessed
file_put_contents(
        dirname(__FILE__) . '/../aquadocs.json',
        json_encode(
                $outputGraph,
                JSON_PRETTY_PRINT
        )
);

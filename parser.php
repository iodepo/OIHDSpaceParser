#! /usr/bin/php
<?php
/**
 * try to read the OAI interface output from a give DSpace instance
 * and transform the DublinCore stuff into a JSON-LD as defined by the ODIS-Arch project
 * https://book.oceaninfohub.org/thematics/docs/README.html
 *
 * @author Arno Lambert <a.lambert@unesco.org>
 * @since 24/02/2021
 *
 */

/*
 * short arguments that can be given
    -v : verbose, give more information on the console
    -q : quiet, give no output on the console
    -h : show the help
 */
$shortopts = "vhq";

/*
 * long arguments that can be given
 *
    --url               (mandatory) url of the DSpace instance, like 'https://aquadocs.org'
    --metadataPrefix    (mandatory) metadataPrefix, like 'oai_dc'
    --output            (mandatory) output file with the complete path, like '/var/www/html/oih.aquadocs.org/aquadocs.json
    --verbose           give more information on the console
    --quiet             give no output on the console
    --help              show the help
 */
$longopts  = array(
    "url:",
    "metadataPrefix:",
    "output:",
    "verbose",
    "quiet",
    "help",
);
$options = getopt($shortopts, $longopts);

$helpMessage = <<<EOF
Usage:
/usr/local/bin/OIHDSpaceParser/parser.php /
    [-v, --verbose] /
    [-h, --help] /
    [-q, --quiete] / 
    --url=https://aquadocs.org /
    --metadataPrefix=oai_dc /
    --output=/var/www/html/oih.aquadocs.org/aquadocs.json

Arguments:
    --url               (mandatory) url of the DSpace instance, like 'https://aquadocs.org'
    --metadataPrefix    (mandatory) metadataPrefix, like 'oai_dc'
    --output            (mandatory) output file with the complete path, like '/var/www/html/oih.aquadocs.org/aquadocs.json
    --verbose, -v       give more information on the console
    --quiet, -q         give no output on the console
    --help, -h          show this help

EOF;
if (isset($options['h'])
    || isset($options['help'])
) {
    print $helpMessage;
    exit(0);
}

$verbose = false;
if (isset($options['v'])
    || isset($options['verbose'])
) {
    $verbose = true;
}

$quiet = false;
if (isset($options['q'])
    || isset($options['quiet'])
) {
    //we cannot have both
    $verbose = false;
    $quiet = true;
}

if (isset($options['url'])) {
    $url = $options['url'];
    if (!preg_match('/^https?:\/\/.*\w+\.\w+/', $url)) {
        print "\n\n**************ERROR**************\n";
        print "we expected a url like 'https://aquadocs.org' or 'http://repository.oceanbestpractices.org' and got '$url'\n";
        exit(1);
    }
    if (!preg_match('/\/$/', $url)) {
        $url .= '/';
    }
    $repoURL = $url . 'oai/request?verb=ListRecords';
} else {
    print "\n\n**************ERROR**************\n we really need a url (https://test.com) to continue\n\n";
    print $helpMessage;
    exit(1);
}

if (isset($options['metadataPrefix'])) {
    $metadataPrefix = $options['metadataPrefix'];
    if (!preg_match('/^[\w\_\-]+$/', $metadataPrefix)) {
        print "\n\n**************ERROR**************\n";
        print "we expected a metadataPrefix like 'oai_dc' and got '$metadataPrefix'\n";
        exit(1);
    }
    $repoURL .= '&metadataPrefix=' . $metadataPrefix;
} else {
    print "\n\n**************ERROR**************\n we really need a metadataPrefix to continue\n\n";
    print $helpMessage;
    exit(1);
}

if (isset($options['output'])) {
    $output = $options['output'];
    try {
        $outputPath = dirname($output);
    } catch (Exception $e) {
        print "\n\n**************ERROR**************\n";
        print "the path to the output file ('$outputPath') does not exist\n";
        print $e->getMessage() . "\n";
        exit(1);
    }
    if (!is_dir($outputPath)) {
        print "\n\n**************ERROR**************\n";
        print "the path to the output file ('$outputPath') does not exist\n";
        exit(1);
    }
    if (!is_writable($outputPath)) {
        print "\n\n**************ERROR**************\n";
        print "the path to the output file ('$outputPath') is not writable\n";
        exit(1);
    }
    if (file_exists($output)
        && !is_writable($output)
        ) {
        print "\n\n**************ERROR**************\n";
        print "the output file ('$output') is not writable\n";
        exit(1);
    }
} else {
    print "\n\n**************ERROR**************\n we really need a output file name (--output=/complete/path/filename.ext) to continue\n\n";
    print $helpMessage;
    exit(1);
}

if ($verbose) {
    print "we will use '$repoURL' to get the info\n";
    print "the output json will be written to '$output'\n";
}

$nextRepoUrl = $repoURL;

//read the content of the page
$dom = new domDocument();
$lastPage = false;
$outputJSON = array();

$count = 0;
while ($nextRepoUrl != '') {
    if ($verbose) {
        print "\n*************************\n";
        print "$nextRepoUrl \n";
    }

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

        if ($verbose) {
            print $oai->responseDate;
        }

        foreach ($oai->ListRecords->record as $record) {
            $count ++;
            if ($verbose) {
                print "\n*************************\n";
                print $record->header->identifier . ' ' . $record->header->datestamp . "\n";
            }

            //subtract the handle id from the identifier
            $identifier = $record->header->identifier;
            $idSuffix = preg_replace(
                '/.+?:(\d+\/\d+)/',
                "$1",
                $identifier
            );

            //to what (sub)sets belongs this document
            foreach ($record->header->setSpec as $set) {
                if ($verbose) {
                    print $set . "\n";
                }
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

if ($verbose) {
    print "\n$count documents found and parsed\n";
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
    'author' => array(
        '@type' => 'Person',
        'name' => 'Arno Lambert',
        'sameAs' => array(
            'https://oceanexpert.org/expert/35711',
            'https://orcid.org/0000-0002-1859-1588'
        )
    ),
    'itemListOrder' => 'https://schema.org/ItemListUnordered',
    'numberOfItems' =>  count($outputJSON),
    'itemListElement' => $outputJSON
);
//finally save the file to be accessed
file_put_contents(
        $output,
        json_encode(
                $outputGraph,
                JSON_PRETTY_PRINT
        )
);

if ($verbose) {
    print "\n********--- done ---*******\n";
}

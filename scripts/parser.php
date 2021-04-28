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

            //get all the contributors
            $contributors = array();
            foreach ($metaData->dc_creator as $contributor) {
                $contributor = str_replace(
                    $pattern,
                    $replace,
                    trim($contributor)
                );
                $contributors[] = $contributor;
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
                "@context" => array(
                    "@vocab" =>  "https://schema.org/"
                ),
                "@type" => "CreativeWork",
                "@id" => "$identifier",
                "name" => "$name",
                "description" => "$descriptionString",
                "url" =>  "$url",
                "identifier" => array(
                    "@id" => "https://hdl.handle.net/$idSuffix",
                    "@type" => "PropertyValue",
                    "propertyID" => "https://hdl.handle.net/",
                    "value" => "$idSuffix",
                    "url" => "https://hdl.handle.net/$idSuffix"
                )
            );

            $contributors = array();
            if (isset($contributors)
                && count($contributors)
            ) {
                foreach ($contributors as $contributor) {
                    array_push(
                $contributors,
                         array(
                            "@type" => "Person",
                            "name" => "$contributor"
                        )
                    );
                }
            }

            if (isset($publisher)
                && $publisher != ''
            ) {
                array_push(
                    $contributors,
                     array(
                        "@type" => "Organization",
                        "name" => "$publisher"
                    )
                );
            }

            if (count($contributors) ) {
                $JSON['contributor'] = $contributors;
            }

            if (isset($keywords)
                && count($keywords)
            ) {
                $JSON["keywords"] = $keywords;
            }
            $outputJSON[] = $JSON;

            /*
            //print "\n*************************\n";
            //print $record->header->identifier . ' ' . $record->header->datestamp . "\n";

            //substract the handle id from the identifier
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
                '"',
                "\n",
                "\r",
                "\t"
            );
            $replace = array(
                '\"',
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

            //get all the contributors
            $contributors = array();
            foreach ($metaData->dc_creator as $contributor) {
                $contributor = str_replace(
                    $pattern,
                    $replace,
                    trim($contributor)
                );
                $contributors[] = $contributor;
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
                $keywords[] = '"' . $keyword . '"';
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

            $JSON = <<<EOT
    {
        "@context": {
            "@vocab": "https://schema.org/"
        },
        "@type": "CreativeWork",
        "@id": "$identifier",
        "name": "$name",
        "description": "$descriptionString",
        "url":  "$url",
        "identifier":
          {
            "@id": "https://handle.net/$idSuffix",
            "@type": "PropertyValue",
            "propertyID": "https://handle.net/",
            "value": "$idSuffix",
            "url": "https://handle.net/$idSuffix"
          }
    EOT;
            if (isset($contributors)
                && count($contributors)
            ) {
                foreach ($contributors as $contributor) {
                    $JSON .= <<< EOT2
        ,"contributor": {
          "@type": "Person",
          "name": "$contributor"
        }
    EOT2;
                }
            }

            if (isset($publisher)
                && $publisher != ''
            ) {
                $JSON .= <<< EOT3
        ,"contributor": {
          "@type": "Organization",
          "legalName": "$publisher"
        }
    EOT3;
            }

            if (isset($keywordString)
                && $keywordString != ''
            ) {
                $JSON .= <<< EOT4
        ,"keywords": [$keywordString]
    EOT4;
            }
            $JSON .= '
    }';
            $outputJSON[] = $JSON;
            */
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

/*
$output = '[' . implode(', ', $outputJSON) . ']';
file_put_contents('../aquadocs.json', $output);
*/
file_put_contents(dirname(__FILE__) . '/../aquadocs.json', json_encode($outputJSON, JSON_PRETTY_PRINT));

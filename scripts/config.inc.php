<?php
/**
 * hold the config settings for the parser
 * no idea if that is really needed at this stage
 *
 * @author Arno Lambert <a.lambert@unesco.org>
 * @since 24/02/2021
 */

//for the staging server
$repoURL = 'http://iodeaquadocs.staging.openrepository.com/oai/request?verb=ListRecords&metadataPrefix=oai_dc';
//next pages are something like
//$repoURL = 'http://iodeaquadocs.staging.openrepository.com/oai/request?verb=ListRecords&resumptionToken=oai_dc////100';
//$repoURL = 'http://iodeaquadocs.staging.openrepository.com/oai/request?verb=ListRecords&resumptionToken=oai_dc////200';
//$repoURL = 'http://iodeaquadocs.staging.openrepository.com/oai/request?verb=ListRecords&resumptionToken=oai_dc////900';

//the real one
//$repoURL = 'http://aquadocs.org/oai/request?verb=ListRecords&metadataPrefix=oai_dc';
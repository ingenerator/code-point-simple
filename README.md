# CodePoint Simple

Builds a simple static file database that maps UK postcodes to lat/lon based on
the [CodePoint Open](http://data.gov.uk/dataset/code-point-open) dataset. 
Create a high-performance AJAX postcode geocoder in minutes!

## Installing and building your database

Clone or download this repository, or just add it to your composer.json:

```json
{
  "require": {
    "ingenerator/code-point-simple" : "0.*"
  }
}
```

You will need to be running a linux operating system with the `unzip` and `wget` 
libraries installed. You will also need php.

### Getting the source data

**CodePoint Open is released under licence by the Ordnance Survey - and you must
ensure your usage of the data complies with the licence requirements**.

[Download CodePoint Open by filling in the download request on the Ordnance Survey website](http://www.ordnancesurvey.co.uk/business-and-government/products/code-point-open.html)
. If you prefer, you can download it directly from a mirror such as [http://parlvid.mysociety.org/os/].

### Build the database

```bash
php code-point-simple.php $URL_OF_CODEPOINT_ZIP $PATH_TO_WRITE_DATABASE
```

CodePoint Open is updated every February, May, August and November. To update
your database, just rerun the code-point-simple.php script.

## Database structure

The database consists of a JSON file for each distinct postcode, grouped by
postcode area, district and then sector. It looks like this:

```
$PATH_TO_WRITE_DATABASE
+---EH
|   +---4
|   |       1.json
|   |       2.json
|   |       centre.json
|   |
|   \---7
|           4.json
|           centre.json
|
\---NE
    \---12
            4.json
            centre.json
```

The JSON for each postcode will look like:

```json
{
  "A":[55.957685,-3.22931],
  "B":[55.957686,-3.22932],
  "C":[55.957687,-3.22933],
}
```

In addition, each postcode district has a centre.json file. This holds the rough
centre-point of all the postcodes in that area, useful for example to centre a map
if a user enters a partial postcode.

```json
{
[55.957685,-3.22933]
}
```

Generating the 13704 files/ 3003 directories will not take very long (roughly 1 minute on our test system). File sizes range from 13 bytes to 10k, overall space claimed on disk is roughly 200MB.

## Server-side database lookups

To geocode a postcode in a server side application, just convert the postcode to
a file path and load the appropriate JSON. For example:

```php
function geocode($postcode)
{
  preg_match('/^([A-Z]{1,2})([0-9]) ?([0-9])([A-Z]{2})?$/', strtoupper($postcode), $matches);
  list($full_match, $area, $district, $sector_prefix, $sector_suffix) = $matches;
  
  foreach (array(
    DB_BASE_DIR."/$area/$district/$sector_prefix.json",
    DB_BASE_DIR."/$area/$district/centre.json"
  ) as $json_path) {
    if (file_exists($json_path)) {
      return json_decode(file_get_contents($json_path), TRUE);
    }
  }
  
  return NULL;
}
```

## Ajax database lookups

Geocoding from the browser is also easy (and very fast, because it simply involves static
file requests).

Build your database somewhere inside the server's document root - for example in a 
/postcodes subdirectory.

Implement the javascript to translate the postcode to an AJAX request for the relevant 
database URL.

```js
postcode_parts = postcode.toUpperCase().match(/^([A-Z]{1,2})([0-9].?) ?([0-9])([A-Z]{2})?$/);
if ( ! postcode_parts[3]) {
  postcode_parts[3] = 'centre';
}
url = '/postcodes/'+postcode_parts[1]+'/'+postcode_parts[2]+'/'+postcode_parts[3]+'.json';
$.get(url, function(result) {
  console.log(result.lat);
  console.log(result.lon);
});
```


## Why not a 'real' database?

Do you really need it? If you're doing huge volumes of lookups for similar 
postcodes then OK, maybe a query cache and all the rest will help. But generally,
your filesystem will do a decent job of caching reads if required. In particular
for AJAX lookups, a decent fileserver will be a whole lot faster than handing 
off to a PHP or other executable, connecting to the database and sending the query.

Plus this way you don't have to remember to exclude the postcode table from your
backup strategy (do you really want to be paying to archive 1.7 million rows of 
readily available read-only third party data)?

And if you need to scale, you can migrate your entire postcode database and onto 
Amazon S3 with a single `s3cmd sync` and a change to the AJAX lookup URL.

Basically, it's one less moving part. It's static data, why not static files?

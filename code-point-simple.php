<?php
/**
 * Converts Code-Point Open data to a hierarchy of directories and files.
 *
 * Code-Point Open data files: see http://parlvid.mysociety.org/os/
 * Data current at time of writing: http://parlvid.mysociety.org/os/codepo_gb-2014-05.zip
 * Download zip archive and unpack to ./data.
 * Call it: php ./code-point-simple.php http://parlvid.mysociety.org/os/codepo_gb-2014-05.zip ./output
 *
 * @author    Matthias Gisder <matthias@ingenerator.com>
 * @copyright 2014 inGenerator Ltd
 * @licence   proprietary
 */

namespace CodePoint;

require_once('App_Coordinates_From_Cartesian.php');

class CodePointSimple {

    const VERBOSE = FALSE;
    const DATA_DIR = './data';
    const TMP_DIR = './tmp';
    const CSV_DIR = 'Data/CSV';
    const HEADER_FILE = 'Doc/Code-Point_Open_Column_Headers.csv';

    /**
     * @param string $url
     * @param string $target_dir
     */
    public static function parse($url, $target_dir)
    {
        self::download($url, $target_dir);

        $filelist = array_filter(glob(self::DATA_DIR . DIRECTORY_SEPARATOR . self::CSV_DIR . '/*'), 'is_file');

        $handle = fopen(self::DATA_DIR . DIRECTORY_SEPARATOR . self::HEADER_FILE, 'r');
        $header = fgetcsv($handle);
        $header_mapping = array_flip($header);
        fclose($handle);

        foreach($filelist as $file){

            $postcode_matrix = array();
            $postcode_district_centre_matrix = array();
            $handle = fopen($file, 'r');

            while($csv = fgetcsv($handle)){
                $postcode = $csv[$header_mapping['PC']];

                preg_match('/^([A-Z]{1,2})([0-9].*?) ?([0-9][A-Z]{2})?$/', strtoupper($postcode), $matches);
                list($full_match, $area, $district, $sector) = $matches;

                $lat_lon = self::lat_lon($csv[$header_mapping['NO']], $csv[$header_mapping['EA']]);

                $sector_prefix = substr($sector, 0, 1);
                $sector_suffix = substr($sector, 1, 2);

                $postcode_matrix[$area][$district][$sector_prefix][$sector_suffix] = array(
                    0 => number_format($lat_lon['latitude'], 4),
                    1 => number_format($lat_lon['longitude'], 4),
                );

                $postcode_district_centre_matrix[$area][$district][] = array(
                    0 => number_format($lat_lon['latitude'], 4),
                    1 => number_format($lat_lon['longitude'], 4),
                );

            }

            fclose($handle);

            foreach($postcode_district_centre_matrix as $area => $value) {
                foreach($value as $district => $postcode_tuples){
                    $json_path = preg_replace('/\/{2,}/', '/', $target_dir . "/$area/$district/centre.json");
                    self::write_json_for_postcode_district_centre($postcode_tuples, $json_path, $area . $district);
                }
            }
            foreach($postcode_matrix as $area => $postcode_tuple) {
                foreach($postcode_tuple as $district => $postcode_tuple2) {

                    foreach($postcode_tuple2 as $sector_prefix => $postcode_tuple3) {

                        $json_path = preg_replace('/\/{2,}/', '/', $target_dir . "/$area/$district/$sector_prefix.json");
                        self::write_json_for_postcode($postcode_tuple3, $json_path);
                    }
                }
            }
        }
    }

    protected function download($url, $target_dir)
    {
        // todo: implement download logic
    }

    protected function postcode_district_centre_data($postcode_tuples, $district_postcode)
    {
        $lat_lon = self::lat_lon_for_district_centre($postcode_tuples);
        $lat = $lat_lon['latitude'];
        $lon = $lat_lon['longitude'];

        $district_centre_data = array(
            0 => $lat,
            1 => $lon,
        );

        return $district_centre_data;
    }

    protected function postcode_data($postcode_tuple)
    {
        $postcode_data = $postcode_tuple;
        return $postcode_data;
    }

    protected function write_json_for_postcode_district_centre($postcode_tuples, $json_path, $district_postcode)
    {
        $postcode_data = self::postcode_district_centre_data($postcode_tuples, $district_postcode);
        $json = json_encode($postcode_data);
        self::do_write_json($json, $json_path);
    }

    protected function write_json_for_postcode($postcode_tuple, $json_path)
    {
        $postcode_data = self::postcode_data($postcode_tuple);
        $json = json_encode($postcode_data);
        self::do_write_json($json, $json_path);
    }

    protected function do_write_json($json, $file_path)
    {
        if(self::VERBOSE === TRUE){
            echo 'Writing data';
            echo "\n   ";
            print_r($json);
            echo "\n   to this file:\n      ";
            print_r($file_path);
            echo "\n           ";
        }

        $pathinfo_array = pathinfo($file_path);
        $new_dir_name = $pathinfo_array['dirname'].'/';

        if (!file_exists($new_dir_name)){
            echo "Creating directory $new_dir_name ...\n";
            mkdir($new_dir_name, 0777, TRUE);

        } else {
            if(!is_dir($new_dir_name)){
                throw(new \Exception('File ' . $new_dir_name . ' already exists'));
            }
        }
        $handle = fopen($file_path, 'w');
        fwrite($handle, $json);
        fclose($handle);

        if(self::VERBOSE === TRUE){
            echo "\ndone";
            echo "\n\n\n";
        }
    }

    protected static function lat_lon($northing, $easting)
    {
        // todo: evaluate accuracy of the results returned.
        // We might later drop in another class do the actual translation.
        $class = new \App_Coordinates_From_Cartesian($easting, $northing);
        $result = $class->Convert();
        return $result;
    }

    public function lat_lon_for_district_centre($data_array)
    {
        // todo: evaluate accuracy of the results returned.
        $minlat = FALSE;
        $minlon = FALSE;
        $maxlat = FALSE;
        $maxlon = FALSE;

        foreach ($data_array as $data_coords) {
            if (isset($data_coords[1])) {
                if ($minlat === FALSE) { $minlat = $data_coords[0]; } else { $minlat = ($data_coords[0] < $minlat) ? $data_coords[0] : $minlat; }
                if ($maxlat === FALSE) { $maxlat = $data_coords[0]; } else { $maxlat = ($data_coords[0] > $maxlat) ? $data_coords[0] : $maxlat; }
                if ($minlon === FALSE) { $minlon = $data_coords[1]; } else { $minlon = ($data_coords[1] < $minlon) ? $data_coords[1] : $minlon; }
                if ($maxlon === FALSE) { $maxlon = $data_coords[1]; } else { $maxlon = ($data_coords[1] > $maxlon) ? $data_coords[1] : $maxlon; }
            }
        }
        $lat = $maxlat - (($maxlat - $minlat) / 2);
        $lon = $maxlon - (($maxlon - $minlon) / 2);

        $lat_lon = array(
            'latitude' => $lat,
            'longitude' => $lon,
        );
        return $lat_lon;
    }
}

CodePointSimple::parse($argv[1], $argv[2]);

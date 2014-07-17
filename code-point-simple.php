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
 * @copyright 2014 Red61 Ltd
 * @licence   proprietary
 */

namespace CodePoint;

require_once('App_Coordinates_From_Cartesian.php');

class CodePointSimple {

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
            $postcode_centre_matrix = array();
            $handle = fopen($file, 'r');

            while($csv = fgetcsv($handle)){
                $postcode = $csv[$header_mapping['PC']];

                preg_match('/^([A-Z]{1,2})([0-9].*?) ?([0-9][A-Z]{2})?$/', strtoupper($postcode), $matches);
                list($full_match, $area, $district, $sector) = $matches;

                $lat_lon = self::lat_lon($csv[$header_mapping['NO']], $csv[$header_mapping['EA']]);

                $postcode_matrix[$postcode] = array(
                    'postcode' => $postcode,
                    'area' => $area,
                    'sector' => $sector,
                    'district' => $district,
                    'parsed_postcode' => $area . $district . ' ' . $sector,
                    'lat' => $lat_lon['latitude'],
                    'lon' => $lat_lon['longitude'],
                );

                $postcode_centre_matrix[$area][$district][] = array(
                    0 => $lat_lon['latitude'],
                    1 => $lat_lon['longitude'],
                );

            }
            fclose($handle);

            foreach($postcode_centre_matrix as $area => $value) {
                foreach($value as $district => $postcode_tuples_array){
                    $json_path_centre = preg_replace('/\/{2,}/', '/', $target_dir . "/$area/$district/centre.json");
                    self::write_json_for_postcode_centre($postcode_tuples_array, $json_path_centre, $area . $district);
                }
            }
            foreach($postcode_matrix as $postcode_tuple) {
                self::write_json_for_postcode($postcode_tuple, $target_dir);
            }
        }
    }


    protected function download($url, $target_dir)
    {
        // todo: implement download logic
    }

    protected static function compute_district_centre_data($postcode_tuples_array, $district_postcode)
    {
        $lat_lon = self::lat_lon_for_district_centre($postcode_tuples_array);
        $lat = $lat_lon['latitude'];
        $lon = $lat_lon['longitude'];

        $district_centre_data = array(
            'match' => 'false',
            'postcode' => $district_postcode,
            'lat' => $lat,
            'lon' => $lon,
        );

        return $district_centre_data;
    }

    protected function write_json_for_postcode_centre($postcode_tuples_array, $json_path_centre, $district_postcode)
    {
        $district_centre_data = self::compute_district_centre_data($postcode_tuples_array, $district_postcode);
        $json_centre = json_encode($district_centre_data);
        self::do_write_json($json_centre, $json_path_centre);
    }

    protected function write_json_for_postcode($postcode_tuple, $target_dir)
    {
        $area = $postcode_tuple['area'];
        $district = $postcode_tuple['district'];
        $sector = $postcode_tuple['sector'];

        $json_path_regular = preg_replace('/\/{2,}/', '/', $target_dir . "/$area/$district/$sector.json");

        $postcode_array_regular = array(
            'match' => 'true',
            'postcode' => $postcode_tuple['parsed_postcode'],
            'lat' => $postcode_tuple['lat'],
            'lon' => $postcode_tuple['lon'],
        );

        $json_regular = json_encode($postcode_array_regular);

        self::do_write_json($json_regular, $json_path_regular);
    }

    protected function do_write_json($json, $file_path)
    {
        echo 'Writing data';
        echo "\n   ";
        print_r($json);
        echo "\n   to this file:\n      ";
        print_r($file_path);
        echo "\n           ";

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

        echo "\ndone";
        echo "\n\n\n";
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

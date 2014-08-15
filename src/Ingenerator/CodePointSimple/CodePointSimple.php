<?php
/**
 * Converts Code-Point Open data to a hierarchy of directories and files.
 *
 * @author    Matthias Gisder <matthias@ingenerator.com>
 * @copyright 2014 inGenerator Ltd
 * @licence   BSD
 */

namespace Ingenerator\CodePointSimple;

use \Ingenerator\CodePointSimple\Helper\FileReader;
use \Ingenerator\CodePointSimple\Helper\LatLonHelper;

class CodePointSimple
{

    const VERBOSE     = FALSE;
    const DATA_DIR    = './data';
    const TMP_DIR     = './tmp';
    const CSV_DIR     = 'Data/CSV';
    const HEADER_FILE = 'Doc/Code-Point_Open_Column_Headers.csv';

    /**
     * @var string
     */
    protected $db_base_dir;

    /**
     * @var array
     */
    protected $header_mapping = array();

    /**
     * @param string $target_dir
     */
    public function init($target_dir)
    {
        $this->db_base_dir = $target_dir;
    }

    /**
     * Parses files in the data directory.
     */
    public function parse()
    {
        $filelist = array_filter(glob(self::DATA_DIR . DIRECTORY_SEPARATOR . self::CSV_DIR . '/*'), 'is_file');

        $this->header_mapping = $this->get_header_mapping();
        foreach ($filelist as $file_path) {

            list($postcode_matrix, $postcode_district_centre_matrix) = $this->parse_file($file_path);
            foreach ($postcode_district_centre_matrix as $area => $value) {
                foreach ($value as $district => $postcode_tuples) {
                    $json_path = preg_replace('/\/{2,}/', '/', $this->db_base_dir . "/$area/$district/centre.json");
                    $postcode_data = LatLonHelper::postcode_district_centre_data($postcode_tuples);
                    $json          = json_encode($postcode_data);
                    $this->do_write($json, $json_path);
                }
            }
            foreach ($postcode_matrix as $area => $postcode_tuple) {
                foreach ($postcode_tuple as $district => $postcode_tuple2) {
                    foreach ($postcode_tuple2 as $sector_prefix => $postcode_tuple3) {
                        $json_path = preg_replace('/\/{2,}/', '/', $this->db_base_dir . "/$area/$district/$sector_prefix.json");
                        $json           = json_encode($postcode_tuple3);
                        $this->do_write($json, $json_path);
                    }
                }
            }
        }
    }

    /**
     * @return array
     */
    protected function get_header_mapping()
    {
        $handle         = fopen(self::DATA_DIR . DIRECTORY_SEPARATOR . self::HEADER_FILE, 'r');
        $header         = fgetcsv($handle);
        fclose($handle);
        return array_flip($header);
    }

    /**
     * Parses an individual file.
     *
     * @param string $file_path
     *
     * @return array
     */
    protected function parse_file($file_path)
    {
        $postcode_matrix                 = array();
        $postcode_district_centre_matrix = array();
        $handle                          = fopen($file_path, 'r');

        while ($csv = fgetcsv($handle)) {
            $postcode = $csv[$this->header_mapping['PC']];

            preg_match('/^([A-Z]{1,2})([0-9].*?) ?([0-9][A-Z]{2})?$/', strtoupper($postcode), $matches);
            list($full_match, $area, $district, $sector) = $matches;

            $lat_lon = LatLonHelper::lat_lon($csv[$this->header_mapping['NO']], $csv[$this->header_mapping['EA']]);

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

        return array($postcode_matrix, $postcode_district_centre_matrix);
    }

    /**
     * @param string $json
     * @param string $file_path
     *
     * @throws \Exception
     */
    protected function do_write($json, $file_path)
    {
        $pathinfo_array = pathinfo($file_path);
        $new_dir_name   = $pathinfo_array['dirname'] . '/';

        if (! file_exists($new_dir_name)) {
            echo "Creating directory $new_dir_name ...\n";
            mkdir($new_dir_name, 0777, TRUE);

        } else {
            if (! is_dir($new_dir_name)) {
                throw(new \Exception('File ' . $new_dir_name . ' already exists'));
            }
        }

        file_put_contents($file_path, $json);
    }

}

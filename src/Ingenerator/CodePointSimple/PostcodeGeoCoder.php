<?php
/**
 * Retrieves latitude and longitude for a given (complete or partial) UK postcode.
 *
 * @author    Matthias Gisder <matthias@ingenerator.com>
 * @copyright 2014 inGenerator Ltd
 * @licence   BSD
 */

namespace Ingenerator\CodePointSimple;

use \Ingenerator\CodePointSimple\Helper\FileReader;

class PostcodeGeoCoder
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
     * @var \Ingenerator\CodePointSimple\Helper\FileReader
     */
    protected $file_reader;

    /**
     * @param \Ingenerator\CodePointSimple\Helper\FileReader $file_reader
     * @param string                                         $target_dir
     */
    public function init(FileReader $file_reader, $target_dir)
    {
        $this->file_reader = $file_reader;
        $this->db_base_dir = $target_dir;
    }

    /**
     * @param $postcode
     *
     * @return array
     */
    public function geocode($postcode)
    {
        preg_match('/^([A-Z]{1,2})([0-9][^\ ]?) ?([0-9]?)([A-Z]{2})?$/', strtoupper($postcode), $matches);

        for ($index = 0; $index < 5; $index ++) {
            if (! isset($matches[$index])) {
                $matches[$index] = '';
            }
        }
        list($full_match, $area, $district, $sector_prefix, $sector_suffix) = $matches;
        foreach (array(
                     $this->db_base_dir . "/$area/$district/$sector_prefix.json",
                     $this->db_base_dir . "/$area/$district/centre.json"
                 ) as $key => $json_path) {
            $result = $this->file_reader->json_read($json_path);
            if ($result !== NULL) {
                if ($key === 0) {
                    if (isset($result[$sector_suffix])) {
                        return array(
                            'lat'   => $result[$sector_suffix][0],
                            'lon'   => $result[$sector_suffix][1],
                            'exact' => TRUE,
                        );
                    }
                } else {
                    return array(
                        'lat'   => $result[0],
                        'lon'   => $result[1],
                        'exact' => FALSE,
                    );
                }
            }
        }
    }

}

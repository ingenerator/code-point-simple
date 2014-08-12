<?php
/**
 * FileReader for json files.
 *
 * @author    Matthias Gisder <matthias@ingenerator.com>
 * @copyright 2014 inGenerator Ltd
 * @licence   proprietary
 */


namespace Ingenerator\CodePointSimple\Helper;


class FileReader {

    public function json_read($path)
    {
        if (file_exists($path)) {
            return json_decode(file_get_contents($path), TRUE);
        }

        return NULL;
    }

}

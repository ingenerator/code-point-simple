<?php

namespace Ingenerator\CodePointSimple\Helper;

use \Ingenerator\CodePointSimple\Helper\AppCoordinatesFromCartesian;


class LatLonHelper {

    public static function postcode_district_centre_data($postcode_tuples)
    {
        $lat_lon = self::lat_lon_for_district_centre($postcode_tuples);
        $lat     = $lat_lon['latitude'];
        $lon     = $lat_lon['longitude'];

        $district_centre_data = array(
            0 => $lat,
            1 => $lon,
        );

        return $district_centre_data;
    }

    public static function lat_lon($northing, $easting)
    {
        // todo: evaluate accuracy of the results returned.
        // We might later drop in another class do the actual translation.
        $lat_lon_converter = new AppCoordinatesFromCartesian($easting, $northing);
        $result            = $lat_lon_converter->Convert();

        return $result;
    }

    public static function lat_lon_for_district_centre($data_array)
    {
        // todo: evaluate accuracy of the results returned.
        $minlat = FALSE;
        $minlon = FALSE;
        $maxlat = FALSE;
        $maxlon = FALSE;

        foreach ($data_array as $data_coords) {
            if (isset($data_coords[1])) {
                if ($minlat === FALSE) {
                    $minlat = $data_coords[0];
                } else {
                    $minlat = ($data_coords[0] < $minlat) ? $data_coords[0] : $minlat;
                }
                if ($maxlat === FALSE) {
                    $maxlat = $data_coords[0];
                } else {
                    $maxlat = ($data_coords[0] > $maxlat) ? $data_coords[0] : $maxlat;
                }
                if ($minlon === FALSE) {
                    $minlon = $data_coords[1];
                } else {
                    $minlon = ($data_coords[1] < $minlon) ? $data_coords[1] : $minlon;
                }
                if ($maxlon === FALSE) {
                    $maxlon = $data_coords[1];
                } else {
                    $maxlon = ($data_coords[1] > $maxlon) ? $data_coords[1] : $maxlon;
                }
            }
        }
        $lat = $maxlat - (($maxlat - $minlat) / 2);
        $lon = $maxlon - (($maxlon - $minlon) / 2);

        $lat_lon = array(
            'latitude'  => $lat,
            'longitude' => $lon,
        );
        return $lat_lon;
    }

} 
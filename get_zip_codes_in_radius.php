<?php

/**
 * Retrieve range of zip codes within given radius as
 *
 * @global wpdb $wpdb
 * @param integer $zip Zip code to query for
 * @param integer $radius Radius in miles
 * @return array|false Array containing results (zip and distance) ordered by proximity
 */
function get_zip_codes_in_radius($zip, $radius){

    global $wpdb;
    $table = $wpdb->prefix.'table_name';

    // need coordinates first for our referenced zip code
    $coords = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT zipLatitude AS lat, zipLongitude AS lon "
            . "FROM {$table} WHERE zip_code = {$zip}",
            $zip
        )
    );

    if (!$coords)
        return false;

    $R   = 6371;  // earth's radius, km
    $k2m = 0.621371; // kilometers to miles
    $rad = floatval($radius * 1.60934); // original radius is in mi, need to convert to km

    //
    // first-cut bounding box (in degrees)
    $maxLat = floatval($coords->lat + rad2deg($rad/$R));
    $minLat = floatval($coords->lat - rad2deg($rad/$R));

    // compensate for degrees longitude getting smaller with increasing latitude
    $maxLon = floatval($coords->lon + rad2deg($rad/$R/cos(deg2rad($coords->lat))));
    $minLon = floatval($coords->lon - rad2deg($rad/$R/cos(deg2rad($coords->lat))));

    // convert origin of filter circle to radians
    $lat = deg2rad($coords->lat);
    $lon = deg2rad($coords->lon);

    $sql = "
SELECT zip_code,
    ROUND((
        ACOS(SIN($lat) * SIN(radians(zipLatitude))
            + COS($lat) * COS(RADIANS(zipLatitude))
            * COS(RADIANS(zipLongitude) - $lon)
        ) * $R) * $k2m) AS distance
FROM (
    SELECT zip_code, zipLatitude, zipLongitude
    FROM {$table}
    WHERE zipLatitude > $minLat AND zipLatitude < $maxLat
      AND zipLongitude > $minLon AND zipLongitude < $maxLon
) AS fc
WHERE ACOS(SIN($lat) * SIN(RADIANS(zipLatitude))
        + COS($lat) * COS(RADIANS(zipLatitude))
        * COS(RADIANS(zipLongitude) - $lon)) * $R < $rad
ORDER BY distance;";

    return $wpdb->get_results($sql);

}

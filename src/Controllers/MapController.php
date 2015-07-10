<?php

namespace WordPress\Tabulate\Controllers;

use WordPress\Tabulate\DB\Database;

class MapController extends ControllerBase {

	public function kml( $args ) {
		$db = new Database( $this->wpdb );
		$table = $db->get_table( $args['table'] );

		// Check that lat and long columns exist.
		$lat = $table->get_column( 'latitude' );
		$lon = $table->get_column( 'longitude' );
		if ( ! $lat || ! $lon ) {
			// @TODO Show error.
			return;
		}

		// Apply filters.
		$filter_param = (isset( $args['filter'] )) ? $args['filter'] : array();
		$table->add_filters( $filter_param );
		$table->add_filter( 'latitude', 'not empty', '' );
		$table->add_filter( 'longitude', 'not empty', '' );

		// Create KML.
		$kml = new \SimpleXMLElement( '<kml />' );
		$kml->addAttribute( 'xmlns', 'http://www.opengis.net/kml/2.2' );
		$kml_doc = $kml->addChild( 'Document' );
		foreach ( $table->get_records( false ) as $record ) {
			$placemark = $kml_doc->addChild( 'Placemark' );
			$placemark->addChild( 'name', $record->get_title() );
			$placemark->addChild( 'description', htmlentities( '<a href="' . $record->get_url() . '">View record.</a>' ) );
			$point = $placemark->addChild( 'Point' );
			$point->addChild( 'coordinates', $record->longitude() . ',' . $record->latitude() );
		}

		// Send to browser.
		$download_name = date( 'Y-m-d' ) . '_' . $table->get_name() . '.kml';
		header( 'Content-Encoding: UTF-8' );
		header( 'Content-type: application/vnd.google-earth.kml+xml; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="'.$download_name.'"' );
		echo $kml->asXML();
		exit;
	}

}

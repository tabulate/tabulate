<?php

namespace WordPress\Tabulate\Controllers;

use WordPress\Tabulate\DB\Database;

class MapController extends ControllerBase {

	/** @var string The name of the POINT column. */
	protected $point_col_name;

	/** @var \WordPress\Tabulate\DB\Table */
	protected $table;

	protected function set_up( $args ) {
		$db = new Database( $this->wpdb );
		$this->table = $db->get_table( $args['table'] );

		// Check that a point column exists.
		$points = $this->table->get_columns( 'point' );
		if ( empty( $points ) ) {
			// @TODO Show error.
			return;
		}
		$point_col = array_shift( $points );
		$this->point_col_name = $point_col->get_name();

		// Apply filters.
		$filter_param = (isset( $args['filter'] )) ? $args['filter'] : array();
		$this->table->add_filters( $filter_param );
		$this->table->add_filter( $this->point_col_name, 'not empty', '' );
	}

	protected function byline() {
		return 'Tabulate ' . TABULATE_VERSION . ' (WordPress plugin)';
	}

	public function osm( $args ) {
		$this->set_up( $args );

		// Create XML.
		$osm = new \SimpleXMLElement( '<osm />' );
		$osm->addAttribute( 'version', '0.6' );
		$osm->addAttribute( 'generator', $this->byline() );
		$id = -1;
		foreach ( $this->table->get_records( false ) as $record ) {
			$geom = \geoPHP::load( $record->{$this->point_col_name}() );
			$node = $osm->addChild( 'node' );
			$node->addAttribute( 'id', $id );
			$id--;
			$node->addAttribute( 'lat', $geom->getY() );
			$node->addAttribute( 'lon', $geom->getX() );
			$node->addAttribute( 'visible', 'true' ); // Required attribute.
			foreach ( $this->table->get_columns() as $col ) {
				if ( $col->get_name() == $this->point_col_name ) {
					// Don't include the geometry column.
					// @todo Exclude other spatial columns?
					continue;
				}
				$tag = $node->addChild( 'tag' );
				$col_name = $col->get_name();
				$tag->addAttribute( 'k', $col_name );
				$fktitle = $col_name . \WordPress\Tabulate\DB\Record::FKTITLE;
				$tag->addAttribute( 'v', $record->$fktitle() );
			}
		}

		// Send to browser.
		$this->send_file( 'osm', 'application/xml', $osm->asXML() );
	}

	public function kml( $args ) {
		$this->set_up( $args );

		// Create KML.
		$kml = new \SimpleXMLElement( '<kml />' );
		$kml->addAttribute( 'xmlns', 'http://www.opengis.net/kml/2.2' );
		$kml_doc = $kml->addChild( 'Document' );
		foreach ( $this->table->get_records( false ) as $record ) {
			$placemark = $kml_doc->addChild( 'Placemark' );
			$placemark->addChild( 'name', $record->get_title() );
			$placemark->addChild( 'description', htmlentities( '<a href="' . $record->get_url() . '">View record.</a>' ) );
			$point = $placemark->addChild( 'Point' );
			$geom = \geoPHP::load( $record->{$this->point_col_name}() );
			$point->addChild( 'coordinates', $geom->getX() . ',' . $geom->getY() );
		}

		// Send to browser.
		$this->send_file( 'kml', 'application/vnd.google-earth.kml+xml', $kml->asXML() );
	}

	public function gpx( $args ) {
		$this->set_up( $args );

		// Create GPX.
		$gpx = new \SimpleXMLElement( '<gpx xmlns:gpxx="http://www.garmin.com/xmlschemas/GpxExtensions/v3" />' );
		$gpx->addAttribute( 'version', '1.1' );
		$gpx->addAttribute( 'xmlns', 'http://www.topografix.com/GPX/1/1' );
		$gpx->addAttribute( 'creator', $this->byline() );
		foreach ( $this->table->get_records( false ) as $record ) {
			$geom = \geoPHP::load( $record->{$this->point_col_name}() );
			$wpt = $gpx->addChild( 'wpt' );
			$wpt->addAttribute( 'lat', $geom->getY() );
			$wpt->addAttribute( 'lon', $geom->getX() );
			$wpt->addChild( 'name', $record->get_title() );
			$wpt->addChild( 'description', htmlentities( '<a href="' . $record->get_url() . '">View record.</a>' ) );
			$extensions = $wpt->addChild( 'extensions' );
			$waypoint_extension = $extensions->addChild( 'gpxx:WaypointExtension', '', 'gpxx' );
			$categories = $waypoint_extension->addChild( 'gpxx:Categories', '', 'gpxx' );
			foreach ( $this->table->get_columns() as $col ) {
				if ( $col->get_name() == $this->point_col_name ) {
					// Don't include the geometry column.
					continue;
				}
				$fktitle = $col->get_name() . \WordPress\Tabulate\DB\Record::FKTITLE;
				$value = $record->$fktitle();
				$categories->addChild( 'gpxx:Categories', $col->get_title() . ": $value", 'gpxx' );
				$waypoint_extension->addChild( 'gpxx:'.$col->get_name(), $value, 'gpxx' );
			}
		}

		// Send to browser.
		$this->send_file( 'gpx', 'application/gpx+xml', $gpx->asXML() );
	}

	protected function send_file($ext, $mime, $content) {
		$download_name = date( 'Y-m-d' ) . '_' . $this->table->get_name() . '.'.$ext;
		header( 'Content-Encoding: UTF-8' );
		header( 'Content-type: ' . $mime . '; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $download_name . '"' );
		echo $content;
		exit;
	}

}

<?php

class Clusterer {
	private $neLat;
	private $neLng;
	private $swLat;
	private $swLng;

	private $minLocations = 1;
	private $numberOfClusters = 50;

	private $buckets = array();
	private $numLat = array();
	private $numLng = array();
	private $coefficientLat = 0;
	private $coefficientLng = 0;

	private $crossBoundsLat = false;
	private $crossBoundsLng = false;

	/**
	 * @param string|float $seLat
	 * @param string|float $seLng
	 * @param string|float $nwLat
	 * @param string|float $nwLng
	 * @param bool $crossBoundsLat
	 * @param bool $crossBoundsLng
	 */
	public function __construct( $neLat, $neLng, $swLat, $swLng, $crossBoundsLat = false, $crossBoundsLng = false ) {
		$this->neLat = $neLat;
		$this->neLng = $neLng;
		$this->swLat = $swLat;
		$this->swLng = $swLng;

		$this->crossBoundsLat = $crossBoundsLat;
		$this->crossBoundsLng = $crossBoundsLng;

		/*
		 * North and east coordinates can actually be lower than south & west.
		 * This will happen when the left side of a map is displaying east and
		 * the right side is displaying west. At the center of the map, we'll
		 * suddenly have coordinates jumping from 360 to -359.
		 * To make calculating things easier, we'll just increase the west
		 * (= negative) coordinates by 360, and consider those to now be east
		 * (and east as west). Now, coordinates will go from 360 to 361.
		 */
		if ( $this->crossBoundsLat ) {
			$neLat = 180 + $this->swLat;
			$this->swLat = $this->neLat;
			$this->neLat = $neLat;
		}
		if ( $this->crossBoundsLng ) {
			$neLng = 360 + $this->swLng;
			$this->swLng = $this->neLng;
			$this->neLng = $neLng;
		}
	}

	/**
	 * Set the minimum amount of locations before clustering
	 *
	 * @param int $limit
	 */
	public function setMinClusterLocations( $limit ) {
		$this->minLocations = $limit;
	}

	/**
	 * Set an approximate amount of clusters.
	 * Approximate in that it also depends on the viewport: less square = less clusters.
	 *
	 * @param int $limit
	 */
	public function setNumberOfClusters( $number ) {
		$this->numberOfClusters = $number;
	}

	/**
	 * @param string|float $lat
	 * @param string|float $lng
	 * @param array $data
	 * @return bool
	 */
	public function addLocation( $lat, $lng, $data = array() ) {
		if ( empty( $this->buckets ) ) {
			$this->createBuckets();
		}

		list( $lat, $lng ) = $this->fixCoords( $lat, $lng );

		list( $indexLat, $indexLng ) = $this->findBucket( $lat, $lng );

		$bucket = isset( $this->buckets[$indexLat][$indexLng] ) ? $this->buckets[$indexLat][$indexLng] : array();

		// cluster already, correct cluster bounds
		if ( isset( $bucket['center'] ) ) {
			$this->buckets[$indexLat][$indexLng] = array(
// bounds are currently unused; commented out because they're relatively expensive to calculate
//				'bounds' => array(
					// these shorthand ifs are equivalent to min() and max(), but faster
//					'neLat' => $bucket['bounds']['neLat'] > $lat ? $bucket['bounds']['neLat'] : $lat,
//					'neLng' => $bucket['bounds']['neLng'] > $lng ? $bucket['bounds']['neLng'] : $lng,
//					'swLat' => $bucket['bounds']['swLat'] < $lat ? $bucket['bounds']['swLat'] : $lat,
//					'swLng' => $bucket['bounds']['swLng'] < $lng ? $bucket['bounds']['swLng'] : $lng,
//				),
				// weighed center
				'center' => array(
					'lat' => ( ( $bucket['center']['lat'] * $bucket['total'] ) + $lat ) / ( $bucket['total'] + 1 ),
					'lng' => ( ( $bucket['center']['lng'] * $bucket['total'] ) + $lng ) / ( $bucket['total'] + 1 ),
				),
				'total' => $bucket['total'] + 1,
			);

		// not cluster yet, but entry limit reached = cluster now
		} elseif ( count( $bucket ) >= $this->minLocations - 1 ) {
			$totalLats = 0;
			$totalLngs = 0;
			$count = 0;
			foreach ( $this->buckets[$indexLat][$indexLng] as $location ) {
				$totalLats += $location['lat'];
				$lng += $location['lng'];
				$count++;
			}

			$this->buckets[$indexLat][$indexLng] = array(
//				'bounds' => array(
//					'neLat' => $lat,
//					'neLng' => $lng,
//					'swLat' => $lat,
//					'swLng' => $lng,
//				),
				'center' => array(
					'lat' => ( $totalLats + $lat ) / ( $count + 1 ),
					'lng' => ( $totalLngs + $lng ) / ( $count + 1 ),
				),
				'total' => $count + 1,
			);

		// entry limit not yet reached, save entry
		} else {
			$this->buckets[$indexLat][$indexLng][] = $data + array( 'lat' => $lat, 'lng' => $lng );
		}

		return true;
	}

	/**
	 * @return array
	 */
	public function getLocations() {
		$locations = array();

		foreach ( $this->buckets as $lat => $lngs ) {
			foreach ( $lngs as $lng => $data ) {
				if ( $this->inBounds( $lat, $lng ) && $data && !isset( $data['center'] ) ) {
					foreach ( $data as $location ) {
						list( $location['lat'], $location['lng'] ) = $this->unfixCoords( $location['lat'], $location['lng'] );

						$locations[] = $location;
					}
				}
			}
		}

		return $locations;
	}

	/**
	 * @return array
	 */
	public function getClusters() {
		$clusters = array();

		foreach ( $this->buckets as $lat => $lngs ) {
			foreach ( $lngs as $lng => $data ) {
				if ( $this->inBounds( $lat, $lng ) && $data && isset( $data['center'] ) ) {
					list( $data['center']['lat'], $data['center']['lng'] ) = $this->unfixCoords( $data['center']['lat'], $data['center']['lng'] );

					$clusters[] = $data;
				}
			}
		}

		return $clusters;
	}

	/**
	 * Based on the given lat & lng coordinates, determine matrix size/structure
	 */
	protected function createBuckets() {
		$totalLat = $this->neLat - $this->swLat;
		$totalLng = $this->neLng - $this->swLng;

		$approxMiddle = round( sqrt( $this->numberOfClusters ) );
		$func = $this->numLat > $this->numLng ? 'floor' : 'ceil'; // the smaller one gets the advantage ;)
		$this->numLat = $func( $totalLat / ( $totalLat + $totalLng ) * $approxMiddle * 2 );
		$this->numLng = $approxMiddle * 2 - $this->numLat;

		// this will be used later to calculate exactly which bucket a coordinate falls into (see findBucket)
		$this->coefficientLat = 1 / ( $totalLat ) * $this->numLat;
		$this->coefficientLng = 1 / ( $totalLng ) * $this->numLng;
	}

	/**
	 * Find the lat & lng indexes of the bucket the given coordinated fit into
	 *
	 * @param string|float $lat
	 * @param string|float $lng
	 * @return array
	 */
	protected function findBucket( $lat, $lng ) {
		return array(
			floor( ( $lat - $this->swLat ) * $this->coefficientLat ),
			floor( ( $lng - $this->swLng ) * $this->coefficientLng ),
		);
	}

	/**
	 * "Fix" coordinates - when leaping from east 360 to west -359, increase
	 * the west coordinated by 360 to make calculating easier.
	 *
	 * @param string|float $lat
	 * @param string|float $lng
	 * @return array [lat, lng]
	 */
	function fixCoords( $lat, $lng ) {
		if ( $this->crossBoundsLat && $lat < $this->swLat ) {
			$lat += 180;
		}
		if ( $this->crossBoundsLng && $lng < $this->swLng ) {
			$lng += 360;
		}

		return array( $lat, $lng );
	}

	/**
	 * Undo "fixed" coordinates. Before returning data, undo "fixed" (increased)
	 * coordinates and return the real coordinates.
	 *
	 * @param string|float $lat
	 * @param string|float $lng
	 * @return array [lat, lng]
	 */
	function unfixCoords( $lat, $lng ) {
		if ( $this->crossBoundsLat && $lat > 180 ) {
			$lat -= 180;
		}
		if ( $this->crossBoundsLng && $lng > 360 ) {
			$lng -= 360;
		}

		return array( $lat, $lng );
	}

	/**
	 * Check if a coordinate is within the defined bounds
	 *
	 * @param string|float $lat
	 * @param string|float $lng
	 * @return bool
	 */
	protected function inBounds( $lat, $lng ) {
		return $lat >= 0 && $lat < $this->numLat && $lng >= 0 && $lng < $this->numLng;
	}
}

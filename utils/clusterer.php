<?php

class Clusterer {
	// bounds
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

	/**
	 * @param string|float $seLat
	 * @param string|float $seLng
	 * @param string|float $nwLat
	 * @param string|float $nwLng
	 */
	public function __construct( $neLat, $neLng, $swLat, $swLng ) {
		$this->neLat = $neLat;
		$this->neLng = $neLng;
		$this->swLat = $swLat;
		$this->swLng = $swLng;
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
			$this->buckets[$indexLat][$indexLng] = array(
//				'bounds' => array(
//					'neLat' => $lat,
//					'neLng' => $lng,
//					'swLat' => $lat,
//					'swLng' => $lng,
//				),
				'center' => array(
					'lat' => $lat,
					'lng' => $lng,
				),
				'total' => count( $bucket ) + 1,
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
					$locations = array_merge( $locations, $data );
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
		$totalLat = $this->neLat > $this->swLat ? $this->neLat - $this->swLat : 180 - ( $this->swLat - $this->neLat );
		$totalLng = $this->neLng > $this->swLng ? $this->neLng - $this->swLng : 360 - ( $this->swLng - $this->neLng );

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
	 * Check if a coordinate is withing the defined bounds
	 *
	 * @param string|float $lat
	 * @param string|float $lng
	 * @return bool
	 */
	protected function inBounds( $lat, $lng ) {
		return $lat >= 0 && $lat < $this->numLat && $lng >= 0 && $lng < $this->numLng;
	}
}

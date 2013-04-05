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
	 * @param mixed $extra
	 * @return bool
	 */
	public function addLocation( $lat, $lng, $extra ) {
		// out of bounds = fail
		if ( $lat > $this->neLat || $lat < $this->swLat || $lng > $this->neLng || $lng < $this->swLng ) {
			return false;
		}

		if ( empty( $this->buckets ) ) {
			$this->createBuckets();
		}

		list( $indexLat, $indexLng ) = $this->findBucket( $lat, $lng );

		$bucket = isset( $this->buckets[$indexLat][$indexLng] ) ? $this->buckets[$indexLat][$indexLng] : array();

		// cluster already, correct cluster bounds
		if ( isset( $bucket['bounds'] ) ) {
			$this->buckets[$indexLat][$indexLng] = array(
				'bounds' => array(
					'neLat' => max( $bucket['bounds']['neLat'], $lat ),
					'neLng' => max( $bucket['bounds']['neLng'], $lng ),
					'swLat' => min( $bucket['bounds']['swLat'], $lat ),
					'swLng' => min( $bucket['bounds']['swLng'], $lng ),
				),
				'total' => $bucket['total'] + 1,
			);

		// not cluster yet, but entry limit reached = cluster now
		} elseif ( count( $bucket ) >= $this->minLocations - 1 ) {
			$this->buckets[$indexLat][$indexLng] = array(
				'bounds' => array(
					'neLat' => $lat,
					'neLng' => $lng,
					'swLat' => $lat,
					'swLng' => $lng,
				),
				'total' => count( $bucket ) + 1,
			);

		// entry limit not yet reached, save entry
		} else {
			$this->buckets[$indexLat][$indexLng][] = array(
				'lat' => $lat,
				'lng' => $lng,
				'extra' => $extra
			);
		}

		return true;
	}

	/**
	 * @return array
	 */
	public function getLocations() {
		$locations = array();

		foreach ( $this->buckets as $lngs ) {
			foreach ( $lngs as $data ) {
				if ( $data && !isset( $data['bounds'] ) ) {
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

		foreach ( $this->buckets as $lngs ) {
			foreach ( $lngs as $data ) {
				if ( $data && isset( $data['bounds'] ) ) {
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
		$this->coefficientLat = 1 / ( $this->neLat - $this->swLat ) * $this->numLat;
		$this->coefficientLng = 1 / ( $this->neLat - $this->swLat ) * $this->numLat;
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
}

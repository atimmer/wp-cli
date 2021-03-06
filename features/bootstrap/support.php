<?php

// Utility functions used by Behat steps

function checkString( $output, $expected, $action ) {
	switch ( $action ) {

	case 'be':
		$r = $expected === rtrim( $output, "\n" );
		break;

	case 'contain':
		$r = false !== strpos( $output, $expected );
		break;

	case 'not contain':
		$r = false === strpos( $output, $expected );
		break;

	default:
		throw new Behat\Behat\Exception\PendingException();
	}

	if ( !$r ) {
		throw new Exception( $output );
	}
}

function compareContents( $expected, $actual ) {
	if ( gettype( $expected ) != gettype( $actual ) ) {
		return false;
	}

	if ( is_object( $expected ) ) {
		foreach ( get_object_vars( $expected ) as $name => $value ) {
			if ( ! compareContents( $value, $actual->$name ) )
				return false;
		}
	} else if ( is_array( $expected ) ) {
		foreach ( $expected as $key => $value ) {
			if ( ! compareContents( $value, $actual[$key] ) )
				return false;
		}
	} else {
		return $expected === $actual;
	}

	return true;
}

/**
 * Compare two strings containing JSON to ensure that @a $actualJson contains at
 * least what the JSON string @a $expectedJson contains.
 *
 * @return whether or not @a $actualJson contains @a $expectedJson
 *     @retval true  @a $actualJson contains @a $expectedJson
 *     @retval false @a $actualJson does not contain @a $expectedJson
 *
 * @param[in] $actualJson   the JSON string to be tested
 * @param[in] $expectedJson the expected JSON string
 *
 * Examples:
 *   expected: {'a':1,'array':[1,3,5]}
 *
 *   1 )
 *   actual: {'a':1,'b':2,'c':3,'array':[1,2,3,4,5]}
 *   return: true
 *
 *   2 )
 *   actual: {'b':2,'c':3,'array':[1,2,3,4,5]}
 *   return: false
 *     element 'a' is missing from the root object
 *
 *   3 )
 *   actual: {'a':0,'b':2,'c':3,'array':[1,2,3,4,5]}
 *   return: false
 *     the value of element 'a' is not 1
 *
 *   4 )
 *   actual: {'a':1,'b':2,'c':3,'array':[1,2,4,5]}
 *   return: false
 *     the contents of 'array' does not include 3
 */
function checkThatJsonStringContainsJsonString( $actualJson, $expectedJson ) {
	$actualValue   = json_decode( $actualJson );
	$expectedValue = json_decode( $expectedJson );

	if ( !$actualValue ) {
		return false;
	}

	return compareContents( $expectedValue, $actualValue );
}

/**
 * Compare two strings to confirm $actualCSV contains $expectedCSV
 * Both strings are expected to have headers for their CSVs.
 * $actualCSV must match all data rows in $expectedCSV
 *
 * @param  string   A CSV string
 * @param  array    A nested array of values
 * @return bool     Whether $actualCSV contains $expectedCSV
 */
function checkThatCsvStringContainsValues( $actualCSV, $expectedCSV ) {
	$actualCSV = array_map( 'str_getcsv', explode( PHP_EOL, $actualCSV ) );

	if ( empty( $actualCSV ) )
		return false;

	// Each sample must have headers
	$actualHeaders = array_values( array_shift( $actualCSV ) );
	$expectedHeaders = array_values( array_shift( $expectedCSV ) );

	// Each expectedCSV must exist somewhere in actualCSV in the proper column
	$expectedResult = 0;
	foreach ( $expectedCSV as $expected_row ) {
		$expected_row = array_combine( $expectedHeaders, $expected_row );
		foreach ( $actualCSV as $actual_row ) {

			if ( count( $actualHeaders ) != count( $actual_row ) )
				continue;

			$actual_row = array_intersect_key( array_combine( $actualHeaders, $actual_row ), $expected_row );
			if ( $actual_row == $expected_row )
				$expectedResult++;
		}
	}

	return $expectedResult >= count( $expectedCSV );
}


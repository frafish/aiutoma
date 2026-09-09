/**
 * Internal dependencies
 */
import type { LogsQuery } from './types';

/**
 * Default values for the logs query.
 */
const DEFAULT_PAGE = 1;
const DEFAULT_PER_PAGE = 25;

export const DEFAULT_VIEW_FIELDS = [
	'timestamp',
	'operation',
	'provider',
	'tokens_total',
	'duration_ms',
	'status',
];

const sanitizeStringArray = ( value: unknown ): string[] => {
	if ( ! Array.isArray( value ) ) {
		return [];
	}

	return Array.from(
		new Set(
			value.filter(
				( item ): item is string =>
					typeof item === 'string' && item.trim().length > 0
			)
		)
	);
};

const normalizeOperationSelection = (
	raw: unknown,
	availableOperations: string[]
): string[] => {
	if ( undefined === raw || ( Array.isArray( raw ) && 0 === raw.length ) ) {
		return [];
	}

	const rawOperations =
		typeof raw === 'string'
			? raw
					.split( ',' )
					.map( ( operation ) => operation.trim() )
					.filter( Boolean )
			: sanitizeStringArray( raw );

	const normalizedOperations = Array.from( new Set( rawOperations ) );

	if ( 0 === availableOperations.length ) {
		return normalizedOperations;
	}

	return normalizedOperations.filter( ( operation ) =>
		availableOperations.includes(


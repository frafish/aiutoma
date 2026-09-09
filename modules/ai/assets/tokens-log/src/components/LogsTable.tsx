/**
 * WordPress dependencies
 */
import { Button, Popover } from '@wordpress/components';
import { useInstanceId } from '@wordpress/compose';
import {
	DataViews,
	type View,
	type Operator,
	type Filter,
	type ViewTable,
} from '@wordpress/dataviews/wp';
import { dateI18n, getSettings } from '@wordpress/date';
import { __, sprintf } from '@wordpress/i18n';
import { useCallback, useMemo, useState } from '@wordpress/element';
import { rotateRight } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import type { ProviderMetadata } from '../../types/providers';
import { DEFAULT_VIEW_FIELDS } from '../query';
import type { FilterOptions, LogEntry, LogsQuery } from '../types';

interface LogsTableProps {
	logs: LogEntry[];
	filterOptions: FilterOptions;
	onViewLog: ( log: LogEntry ) => void;
	loading: boolean;
	totalPages: number;
	total: number;
	query: LogsQuery;
	setQuery: React.Dispatch< React.SetStateAction< LogsQuery > >;
	providerMetadata: Record< string, ProviderMetadata >;
	connectorsUrl: string;
	onRefresh: () => void;
}

/**
 * View-only properties that DataViews manages but are not part of the API
 * query. These are tracked separately to survive the query round-trip.
 */
interface ViewConfig {
	filters: Filter[];
	fields: string[];
	layout: NonNullable< ViewTable[ 'layout' ] >;
}

const formatTimestamp = ( timestamp: string ): string => {
	const { formats } = getSettings();
	return dateI18n( `${ formats.date } ${ formats.time }`, timestamp + 'Z' );
};

const formatDuration = ( ms: number | null ): string => {
	if ( null === ms ) {
		return '-';
	}

	if ( ms < 1000 ) {
		return `${ ms }ms`;
	}

	return `${ ( ms / 1000 ).toFixed( 1 ) }s`;
};

const formatTokens = ( tokens: number | null ): string => {
	if ( null === tokens ) {
		return '-';
	}

	if ( tokens >= 1000 ) {
		return `${ ( tokens / 1000 ).toFixed( 1 ) }K`;
	}

	return tokens.toLocaleString();
};

const formatTokensPerSecond = ( value: number | null ): string => {
	if ( null === value ) {
		return '-';
	}

	if ( value >= 1000 ) {
		return `${ ( value / 1000 ).toFixed( 1 ) }K`;
	}

	return value.toFixed( 1 );
};

const getStatusClass = ( status: string ): string => {
	switch ( status ) {
		case 'success':
			return 'ai-request-logs__status--success';
		case 'error':
			return 'ai-request-logs__status--error';
		case 'timeout':
			return 'ai-request-logs__status--timeout';
		default:
			return '';
	}
};

const formatSelectLabel = ( value: string ): string =>
	value
		.split( '_' )
		.map( ( part ) => part.charAt( 0 ).toUpperCase() + part.slice( 1 ) )
		.join( ' ' );

const getRequestKind = ( entry: LogEntry ): string => {
	const raw = entry.context?.request_kind;
	return typeof raw === 'string' ? raw : 'text';
};

const getSourceLabel = ( entry: LogEntry ): string | null => {
	const source = entry.context?.source;

	if ( ! source || typeof source !== 'object' ) {
		return null;
	}

	const sourceName =
		'name' in source && typeof source.name === 'string'
			? source.name
			: null;
	const sourceType =
		'type' in source && typeof source.type === 'string'
			? source.type
			: null;

	if ( sourceName && sourceType ) {
		return `${ sourceName } (${ sourceType })`;
	}

	return sourceName ?? sourceType ?? null;
};

/**
 * Builds the initial filters array from a persisted LogsQuery so the
 * DataViews chip UI reflects saved filter state on first render.
 *
 * @param query Persisted logs query.
 */
const buildFiltersFromQuery = ( query: LogsQuery ): Filter[] => {
	const filters: Filter[] = [];

	if ( query.type ) {
		filters.push( {
			field: 'type',
			operator: 'is' as Operator,
			value: query.type,
		} );
	}
	if ( query.status ) {
		filters.push( {
			field: 'status',
			operator: 'is' as Operator,
			value: query.status,
		} );
	}
	if ( query.provider ) {
		filters.push( {
			field: 'provider',
			operator: 'is' as Operator,
			value: query.provider,
		} );
	}
	if ( query.operation.length > 0 ) {
		filters.push( {
			field: 'operation',
			operator: 'isAny' as Operator,
			value: query.operation,
		} );
	}
	if ( query.tokensFilter ) {
		filters.push( {
			field: 'tokensFilter',
			operator: 'is' as Operator,
			value: query.tokensFilter,
		} );
	}
	if ( query.userId ) {
		filters.push( {
			field: 'user_id',
			operator: 'is' as Operator,
			value: query.userId,
		} );
	}

	return filters;
};

/**
 * Extracts a string filter value, returning an empty string when the
 * filter is absent or has no concrete value (e.g. just opened, value
 * is still `undefined`).
 *
 * @param filters Active DataViews filters.
 * @param field   Field id to look up.
 */
const extr


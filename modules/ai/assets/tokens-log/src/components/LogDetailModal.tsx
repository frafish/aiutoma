/**
 * WordPress dependencies
 */
import { Button, Modal } from '@wordpress/components';
import { dateI18n, getSettings } from '@wordpress/date';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { useCopyToClipboardFeedback } from '../../../hooks/use-copy-to-clipboard-feedback';
import type { LogEntry } from '../types';

interface LogDetailModalProps {
	log: LogEntry;
	onClose: () => void;
}

const formatTimestamp = ( timestamp: string ): string => {
	const { formats } = getSettings();
	return dateI18n( `${ formats.date } ${ formats.time }`, timestamp + 'Z' );
};

const formatKindLabel = ( value: string ): string =>
	value
		.split( '_' )
		.map( ( part ) => part.charAt( 0 ).toUpperCase() + part.slice( 1 ) )
		.join( ' ' );

const formatTokensPerSecond = ( value: number | null ): string => {
	if ( value === null ) {
		return '-';
	}
	if ( value >= 1000 ) {
		return ( value / 1000 ).toFixed( 1 ) + 'K';
	}
	return value.toFixed( 1 );
};

const getSourceData = (
	log: LogEntry
): { label: string | null; file: string | null } => {
	const source = log.context?.source;

	if ( ! source || typeof source !== 'object' ) {
		return {
			label: null,
			file: null,
		};
	}

	const sourceName =
		'name' in source && typeof source.name === 'string'
			? source.name
			: null;
	const sourceType =
		'type' in source && typeof source.type === 'string'
			? source.type
			: null;
	const sourceFile =
		'file' in source && typeof source.file === 'string'
			? source.file
			: null;

	return {
		label:
			sourceName && sourceType
				? `${ sourceName } (${ sourceType })`
				: sourceName ?? sourceType ?? null,
		file: sourceFile,
	};
};

const LogDetailModal: React.FC< LogDetailModalProps > = ( {
	log,
	onClose,
} ) => {
	const context = log.context;
	const inputPreview =
		typeof context?.input_preview === 'string'
			? context.input_preview
			: null;
	const outputPreview =
		typeof context?.output_preview === 'string'
			? context.output_preview
			: null;
	const requestKind =
		typeof context?.request_kind === 'string' ? context.request_kind : null;
	const sourceData = getSourceData( log );

	const imageUrlValue = context?.image_urls;
	const imageUrls = Array.isArray( imageUrlValue )
		? imageUrlValue.filter(
				( url ): url is string =>
					typeof url === 'string' && url.length > 0
		  )
		: [];

	const base64Value = context?.image_base64_samples;
	const base64Images = Array.isArray( base64Value )
		? base64Value
		


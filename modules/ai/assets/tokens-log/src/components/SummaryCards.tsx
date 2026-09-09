/**
 * WordPress dependencies
 */
import { Card, CardBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import type { LogSummary } from '../types';

interface SummaryCardsProps {
	summary: LogSummary;
	loading: boolean;
}

const formatNumber = ( num: number ): string => {
	if ( num >= 1000000 ) {
		return ( num / 1000000 ).toFixed( 1 ) + 'M';
	}
	if ( num >= 1000 ) {
		return ( num / 1000 ).toFixed( 1 ) + 'K';
	}
	return num.toLocaleString();
};

const formatDuration = ( ms: number ): string => {
	if ( m


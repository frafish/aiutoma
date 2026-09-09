/**
 * WordPress dependencies
 */
import { SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import type { SummaryPeriod } from '../types';

interface HeaderPeriodSelectorProps {
	period: SummaryPeriod;
	onPeriodChange: ( period: SummaryPeriod ) => void;
	loading: boolean;
}

const HeaderPeriodSelector: React.FC< HeaderPeriodSelectorProps > = ( {
	period,
	onPeriodChange,
	loading,
} ) => (
	<SelectCon


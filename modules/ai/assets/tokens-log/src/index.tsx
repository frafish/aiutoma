/**
 * Internal dependencies
 */
import './index.scss';

/**
 * WordPress dependencies
 */
import { Page } from '@wordpress/admin-ui';
import apiFetch from '@wordpress/api-fetch';
import { Notice } from '@wordpress/components';
import { dispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';
import {
	createRoot,
	useCallback,
	useDeferredValue,
	useEffect,
	useState,
} from '@wordpress/element';

/**
 * Internal dependencies
 */
import AiIcon from '../../../routes/ai-home/ai-icon';
import { getErrorMessage } from '../../utils/errors';
import HeaderPeriodSelector from './components/HeaderPeriodSelector';
import LogDetailModal from './components/LogDetailModal';
import LogsTable from './components/LogsTable';
import {
	getDefaultLogsQuery,
	normalizeLogsQuery,
	serializeOperationSelection,
} from './query';
import SettingsPanel from './components/SettingsPanel';
import SummaryCards from './components/SummaryCards';
import type {
	FilterOptions,
	LocalizedSettings,
	LogEntry,
	LogSummary,
	LogsQuery,
	SummaryPeriod,
} from './types';

const settings: LocalizedSettings =
	window.aiRequestLogsSettings ??
	( () => {
		throw new Error( 'aiRequestLogsSettings is not defined.' );
	} )();

const providerMetadata = settings.providerMetadata ?? {};
const connectorsUrl = settings.connectorsUrl;
const LOGS_QUERY_STORAGE_KEY = 'ai.requestLogs.query';
const INITIAL_FILTERS: FilterOptions = {
	...settings.initialState.filters,
	operations: settings.initialState.filters.operations ?? [],
};

apiFetch.use( apiFetch.createNonceMiddleware( settings.rest.nonce ) );
apiFetch.use( apiFetch.createRootURLMiddleware( settings.rest.root ) );

const showNotice = (
	status: 'success' | 'error' | 'warning',
	message: string
) =>
	dispatch( noticesStore ).createNotice( status, message, {
		type: 'snackbar',
	} );

const PERIOD_OFFSETS_MS: Record< Exclude< SummaryPeriod, 'all' >, number > = {
	minute: 60 * 1000,
	hour: 60 * 60 * 1000,
	day: 24 * 60 * 60 * 1000,
	week: 7 * 24 * 60 * 60 * 1000,
	month: 30 * 24 * 60 * 60 * 1000,
};

/**
 * Resolves the GMT MySQL datetime that bounds the start of the selected period.
 * Returns null for 'all' (no time-based filter).
 */
const periodToDateFrom = ( period: SummaryPeriod ): string | null => {
	if ( period === 'all' ) {
		return null;
	}

	const since = new Date( Date.now() - PERIOD_OFFSETS_MS[ period ] );
	return since.toISOString().slice( 0, 19 ).replace( 'T', ' ' );
};

const getInitialLogsQuery = (): LogsQuery => {
	try {
		const persisted = window.localStorage.getItem( LOGS_QUERY_STORAGE_KEY );

		if ( ! persisted ) {
			return getDefaultLogsQuery();
		}

		return normalizeLogsQuery(
			JSON.parse( persisted ),
			INITIAL_FILTERS.operations
		);
	} catch {
		return getDefaultLogsQuery();
	}
};

const App: React.FC = () => {
	const [ summary, setSummary ] = useState< LogSummary >(
		settings.initialState.summary
	);
	const [ summaryPe


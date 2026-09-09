/**
 * Internal dependencies
 */
import type { ProviderMetadataMap } from '../types/providers';

export type SummaryPeriod =
	| 'minute'
	| 'hour'
	| 'day'
	| 'week'
	| 'month'
	| 'all';

export interface LogEntrySource {
	type?: string;
	slug?: string;
	name?: string;
	file?: string;
}

export interface LogEntryContext extends Record< string, unknown > {
	input_preview?: string;
	output_preview?: string;
	request_kind?: string;
	image_urls?: unknown[];
	image_base64_samples?: unknown[];
	source?: LogEntrySource | Record< string, unknown >;
}

export interface LogEntry {
	id: string;
	timestamp: string;
	type: 'ai_client' | 'mcp_tool' | 'ability';
	operation: string;
	provider: string | null;
	model: string | null;
	duration_ms: number | null;
	tokens_inpu


/**
 * WordPress dependencies
 */
import { Button, Card, CardBody, CardHeader } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useRef, useState } from '@wordpress/element';

interface SettingsPanelProps {
	hasLogs: boolean;
	onPurgeLogs: () => void;
	purging: boolean;
}

const SettingsPanel: React.FC< SettingsPanelProps > = ( {
	hasLogs,
	onPurgeLogs,
	purging,
} ) => {
	const [ showPurgeConfirm, setShowPurgeConfirm ] = useState( false );
	const focusPurgeButtonRef = useRef< boolean >( false );
	const focusCancelButtonRef = useRef< boolean >( false );

	function focusPurgeButtonOnMount( node: HTMLButtonElement | null ) {
		if ( focusPurgeButtonRef.current && node ) {
			node.focus();
			focusPurgeButtonRef.current = false;
		}
	}

	function focusCancelButtonOnMount( node: HTMLButtonElement | null ) {
		if ( focusCancelButtonRef.current && node ) {
			node.focus();
			focusCancelButtonRef.curr


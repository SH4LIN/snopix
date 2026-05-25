import { useEffect } from 'react';

interface Props {
	message: string;
	onDismiss: () => void;
	duration?: number;
}

/**
 * Pill-shaped transient toast pinned to the bottom-center of the viewport.
 * Auto-dismisses after `duration` ms (default 2200).
 *
 * @param {Props}      props          Component props.
 * @param {string}     props.message  Body copy.
 * @param {() => void} props.onDismiss Fired when the auto-dismiss timer fires.
 * @param {number=}    props.duration Optional override for the dismiss delay.
 *
 * @return {JSX.Element}
 */
export default function Toast({ message, onDismiss, duration = 2200 }: Props) {
	useEffect(() => {
		const t = setTimeout(onDismiss, duration);
		return () => clearTimeout(t);
	}, [onDismiss, duration]);

	return <div className="snopix-toast">{message}</div>;
}

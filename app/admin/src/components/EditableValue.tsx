import { useRef, useState } from 'react';
import { __ } from '@wordpress/i18n';

interface Props {
	value: number;
	min: number;
	max: number;
	step?: number;
	suffix: string;
	onChange: (v: number) => void;
}

/**
 * Click-to-edit numeric readout for a slider.
 *
 * Design: `design/admin-editable-slider.jsx`. Resting state is plain text with
 * a hover affordance; clicking turns it into a content-width numeric field.
 * Typing an in-range number updates `onChange` live (so a bound slider tracks);
 * Enter / blur commits (clamped and snapped to `step`); Escape reverts to the
 * value held when editing began. Resting button and edit box share identical
 * padding + border so entering edit mode never shifts surrounding layout.
 *
 * @param {Props} props          Component props.
 * @param {number} props.value   Current value (also the slider position).
 * @param {number} props.min     Lower bound (inclusive).
 * @param {number} props.max     Upper bound (inclusive).
 * @param {number} [props.step]  Snap increment. Defaults to 1.
 * @param {string} props.suffix  Unit text shown after the number (e.g. "%").
 * @param {(v: number) => void} props.onChange Commit / live-update handler.
 *
 * @return {JSX.Element}
 */
export default function EditableValue({
	value,
	min,
	max,
	step = 1,
	suffix,
	onChange,
}: Props) {
	const [editing, setEditing] = useState(false);
	const [draft, setDraft] = useState(String(value));
	const cancelled = useRef(false);
	const startValue = useRef(value);

	const snap = (n: number) => {
		const clamped = Math.min(max, Math.max(min, n));
		return Math.round((clamped - min) / step) * step + min;
	};

	// Push live changes to the slider as the user types, but only for an
	// in-range number so partial input (e.g. "5" before "50") doesn't snap.
	// Cap at `max` as they type so the value can never exceed the upper bound;
	// the lower bound stays uncapped so partial input is still typable.
	const onDraftChange = (text: string) => {
		const clean = text.replace(/[^0-9]/g, '').slice(0, 4);
		const n = parseInt(clean, 10);
		if (!Number.isNaN(n) && n > max) {
			setDraft(String(max));
			onChange(snap(max));
			return;
		}
		setDraft(clean);
		if (!Number.isNaN(n) && n >= min && n <= max) {
			onChange(snap(n));
		}
	};

	const commit = () => {
		if (cancelled.current) {
			cancelled.current = false;
			onChange(startValue.current);
			setEditing(false);
			return;
		}
		const n = parseInt(draft, 10);
		onChange(snap(Number.isNaN(n) ? startValue.current : n));
		setEditing(false);
	};

	if (editing) {
		return (
			<span className="snopix-range-edit">
				<input
					className="snopix-range-edit__input"
					type="text"
					inputMode="numeric"
					aria-label={__('Exact value', 'snopix')}
					value={draft}
					style={{ width: `${Math.max(2, draft.length)}ch` }}
					autoFocus
					onFocus={(e) => e.target.select()}
					onChange={(e) => onDraftChange(e.target.value)}
					onKeyDown={(e) => {
						if (e.key === 'Enter') {
							e.currentTarget.blur();
						} else if (e.key === 'Escape') {
							cancelled.current = true;
							e.currentTarget.blur();
						}
					}}
					onBlur={commit}
				/>
				<span className="snopix-range-value__suffix">{suffix}</span>
			</span>
		);
	}

	return (
		<button
			type="button"
			className="snopix-range-value"
			title={__('Click to type an exact value', 'snopix')}
			onClick={() => {
				startValue.current = value;
				setDraft(String(value));
				setEditing(true);
			}}
		>
			{value}
			<span className="snopix-range-value__suffix">{suffix}</span>
		</button>
	);
}

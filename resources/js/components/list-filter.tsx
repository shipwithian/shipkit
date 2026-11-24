import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

const ALL = 'all';

type Props = {
    /** The chosen option's value, or null for all. */
    value: string | number | null;
    onChange: (value: string | null) => void;
    /** Shown when nothing is chosen, such as "All roles". */
    allLabel: string;
    options: { value: string | number; label: string }[];
};

/**
 * One filter of a list page's toolbar: a choice of one option, or all of them.
 */
export function ListFilter({ value, onChange, allLabel, options }: Props) {
    return (
        <Select
            value={value === null ? ALL : String(value)}
            onValueChange={(chosen) => onChange(chosen === ALL ? null : chosen)}
        >
            <SelectTrigger className="sm:w-48" aria-label={allLabel}>
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value={ALL}>{allLabel}</SelectItem>
                {options.map((option) => (
                    <SelectItem key={option.value} value={String(option.value)}>
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

import type { EventCategory } from '@/types/calendar';

interface CategoryBadgeProps {
    category: EventCategory;
    className?: string;
}

export default function CategoryBadge({
    category,
    className,
}: CategoryBadgeProps) {
    const getContrastColor = (hexColor: string): string => {
        const color = hexColor.replace('#', '');
        const r = parseInt(color.substring(0, 2), 16);
        const g = parseInt(color.substring(2, 4), 16);
        const b = parseInt(color.substring(4, 6), 16);
        const brightness = (r * 299 + g * 587 + b * 114) / 1000;
        return brightness > 155 ? '#000000' : '#FFFFFF';
    };

    const backgroundColor = category.color || '#6B7280';
    const textColor = getContrastColor(backgroundColor);

    return (
        <span
            className={`badge ${className || ''}`}
            style={{
                backgroundColor,
                color: textColor,
                cursor: 'pointer',
                fontSize: '0.875rem',
                padding: '0.375rem 0.75rem',
                borderRadius: '0.25rem',
                fontWeight: '500',
            }}
        >
            {category.name}
        </span>
    );
}

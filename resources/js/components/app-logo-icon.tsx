import type { SVGAttributes } from 'react';

/**
 * Zetago monogram: a geometric "z" plus the brand dot.
 * The "z" follows `currentColor` (set it with text-*), the dot is always
 * the brand primary.
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
            <path d="M7 9h22v5.5L15.5 26H29v5.5H7V26l13.5-11.5H7z" />
            <circle cx="34.5" cy="28.75" r="3.25" className="fill-primary" />
        </svg>
    );
}

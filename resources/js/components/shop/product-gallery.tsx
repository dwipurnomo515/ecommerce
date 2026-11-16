import { ImageOff } from 'lucide-react';
import { useRef, useState } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    images: { id: number; url: string }[];
    name: string;
};

/**
 * Phone: swipe through full-width photos (native scroll-snap, no library)
 * with a "2 / 5" counter. Desktop: one large photo plus thumbnails.
 */
export function ProductGallery({ images, name }: Props) {
    const [current, setCurrent] = useState(0);
    const track = useRef<HTMLDivElement>(null);

    if (images.length === 0) {
        return (
            <div className="grid aspect-square place-items-center rounded-xl bg-muted text-muted-foreground">
                <span className="grid justify-items-center gap-2 text-sm">
                    <ImageOff className="size-8" aria-hidden />
                    Penjual belum menambahkan foto
                </span>
            </div>
        );
    }

    const showThumb = (i: number) => {
        setCurrent(i);
        // Keep the phone carousel in sync if the window is resized later.
        track.current?.scrollTo({ left: i * track.current.clientWidth });
    };

    return (
        <div className="grid gap-3">
            {/* Phone: swipeable track */}
            <div className="relative md:hidden">
                <div
                    ref={track}
                    className="-mx-4 flex snap-x snap-mandatory [scrollbar-width:none] overflow-x-auto"
                    onScroll={(e) => {
                        const el = e.currentTarget;
                        setCurrent(Math.round(el.scrollLeft / el.clientWidth));
                    }}
                >
                    {images.map((image, i) => (
                        <img
                            key={image.id}
                            src={image.url}
                            alt={`${name}, foto ${i + 1} dari ${images.length}`}
                            // First photo is what the buyer came for: load it now.
                            loading={i === 0 ? 'eager' : 'lazy'}
                            className="aspect-square w-full shrink-0 snap-center bg-muted object-cover"
                        />
                    ))}
                </div>
                {images.length > 1 && (
                    <span className="absolute right-3 bottom-3 rounded-md bg-background/90 px-2 py-0.5 text-xs font-medium tabular-nums">
                        {current + 1} / {images.length}
                    </span>
                )}
            </div>

            {/* Desktop: large photo + thumbnails */}
            <div className="hidden gap-3 md:grid">
                <img
                    src={images[current]?.url}
                    alt={`${name}, foto ${current + 1} dari ${images.length}`}
                    className="aspect-square w-full rounded-xl bg-muted object-cover"
                />
                {images.length > 1 && (
                    <ul className="grid grid-cols-6 gap-2">
                        {images.map((image, i) => (
                            <li key={image.id}>
                                <button
                                    type="button"
                                    onClick={() => showThumb(i)}
                                    aria-label={`Lihat foto ${i + 1}`}
                                    aria-current={i === current}
                                    className={cn(
                                        'block aspect-square w-full overflow-hidden rounded-md border-2 outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
                                        i === current
                                            ? 'border-primary'
                                            : 'border-transparent opacity-80 hover:opacity-100',
                                    )}
                                >
                                    <img
                                        src={image.url}
                                        alt=""
                                        loading="lazy"
                                        className="size-full object-cover"
                                    />
                                </button>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </div>
    );
}

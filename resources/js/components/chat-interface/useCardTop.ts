import { useLayoutEffect, useState } from "react";

import type { RefObject } from "react";

/**
 * Where the chat card starts, in pixels from the top of the document, so the
 * card can fill the viewport below it whatever chrome a page puts above.
 * `null` until measured.
 *
 * Re-measured when the window resizes, when the page's content changes height
 * (a header card wrapping, a banner appearing), and when the card itself
 * changes size, which includes a hidden tab panel being shown again.
 */
export default function useCardTop(
    cardRef: RefObject<HTMLElement | null>,
): number | null {
    const [top, setTop] = useState<number | null>(null);

    useLayoutEffect(() => {
        const card = cardRef.current;
        if (!card) {
            return;
        }

        const measure = () => {
            // A card inside a hidden tab panel has no box; keep the last
            // measurement until it is shown.
            if (card.getClientRects().length === 0) {
                return;
            }

            setTop(
                Math.round(card.getBoundingClientRect().top + window.scrollY),
            );
        };

        measure();

        const observer = new ResizeObserver(measure);
        observer.observe(card);
        observer.observe(document.body);
        window.addEventListener("resize", measure);

        return () => {
            observer.disconnect();
            window.removeEventListener("resize", measure);
        };
    }, [cardRef]);

    return top;
}

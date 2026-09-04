import { useEffect, useRef, useState, type ReactNode } from 'react';
import { toast } from 'sonner';
import { Crosshair, MapPin } from 'lucide-react';
import { ResponsiveFormPanel } from '@/components/ResponsiveFormPanel';
import { Button } from '@/components/ui/button';
import { haptic } from '@/lib/format';
import type { Map as MapLibreMap, StyleSpecification } from 'maplibre-gl';

const OSM_STYLE: StyleSpecification = {
    version: 8,
    sources: {
        osm: {
            type: 'raster',
            tiles: ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'],
            tileSize: 256,
            attribution:
                '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        },
    },
    layers: [{ id: 'osm', type: 'raster', source: 'osm' }],
};

const JAKARTA_CENTER = { lat: -6.2, lon: 106.8167 };

/** MapLibre 6 renders through WebGL2; without it the map is a black canvas. */
function webgl2Supported(): boolean {
    try {
        const canvas = document.createElement('canvas');
        return canvas.getContext('webgl2') != null;
    } catch {
        return false;
    }
}

interface LocationPickerPanelProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: ReactNode;
    /** Existing pin to re-center on (e.g. the customer's saved coordinates). */
    initialCoords?: { lat: number; lon: number } | null;
    /** Store origin coordinates — used as the starting view when no pin exists yet. */
    originCoords?: { lat: number; lon: number } | null;
    onConfirm: (lat: number, lon: number) => void;
}

/**
 * Shared crosshair-center map picker (Drawer mobile / Sheet desktop).
 * The user pans the map so the center crosshair sits on the target, then
 * confirms via "Pakai Lokasi Ini"; onConfirm receives map.getCenter().
 * Basemap is the standard OpenStreetMap raster tiles (tile.openstreetmap.org).
 * MapLibre is dynamically imported on first open to keep the main bundle lean.
 * On open with no existing pin, device location is auto-requested (like map
 * apps) and the map recenters on the fix unless the user already panned.
 */
export function LocationPickerPanel({
    open,
    onOpenChange,
    title,
    initialCoords,
    originCoords,
    onConfirm,
}: LocationPickerPanelProps) {
    const containerRef = useRef<HTMLDivElement | null>(null);
    const mapRef = useRef<MapLibreMap | null>(null);
    /** GPS fix that arrived before the map instance existed, applied on creation. */
    const pendingFixRef = useRef<{ lat: number; lon: number } | null>(null);
    const [ready, setReady] = useState(false);
    const [locating, setLocating] = useState(false);
    const [glUnsupported, setGlUnsupported] = useState(false);
    const [center, setCenter] = useState(
        () => initialCoords ?? originCoords ?? JAKARTA_CENTER,
    );

    useEffect(() => {
        if (!open) return;
        let cancelled = false;
        let observer: ResizeObserver | null = null;
        let map: MapLibreMap | null = null;
        // Set once the user pans/zooms the map themselves; an auto-locate fix
        // arriving after that must not yank the map out from under them.
        let userInteracted = false;

        const start = initialCoords ?? originCoords ?? JAKARTA_CENTER;
        setCenter(start);
        pendingFixRef.current = null;

        // Auto-request device location on open (map-app behavior), but only
        // when there is no existing pin to start from. Fired synchronously at
        // open so Chrome's geolocation prompt rides the panel-opening click's
        // user activation — waiting for map/style load can exhaust the window
        // and Chrome then suppresses the prompt entirely.
        if (initialCoords == null && 'geolocation' in navigator) {
            const applyFix = (lat: number, lon: number) => {
                if (cancelled || userInteracted) return;
                const existing = mapRef.current;
                if (existing) {
                    existing.jumpTo({ center: [lon, lat], zoom: 15 });
                } else {
                    pendingFixRef.current = { lat, lon };
                }
                setCenter({ lat, lon });
            };
            const attempt = () => {
                navigator.geolocation.getCurrentPosition(
                    (pos) =>
                        applyFix(pos.coords.latitude, pos.coords.longitude),
                    // Silent on failure: the manual "Lokasi Saya" button still
                    // surfaces the full per-error guidance toasts.
                    () => {},
                    { enableHighAccuracy: true, timeout: 15000, maximumAge: 30000 },
                );
            };
            if (typeof navigator.permissions?.query === 'function') {
                navigator.permissions
                    .query({ name: 'geolocation' })
                    .then((status) => {
                        // A denied site never re-prompts; skip the doomed call.
                        if (!cancelled && status.state !== 'denied') attempt();
                    })
                    .catch(() => {
                        if (!cancelled) attempt();
                    });
            } else {
                attempt();
            }
        }

        (async () => {
            const [maplibregl] = await Promise.all([
                import('maplibre-gl'),
                import('maplibre-gl/dist/maplibre-gl.css'),
            ]);
            if (cancelled || !containerRef.current) return;

            // Fail gracefully instead of a black map (and a possible
            // blackened page on close when the context teardown takes the
            // GPU down with it on already-failing drivers).
            if (!webgl2Supported()) {
                setGlUnsupported(true);
                return;
            }

            // Default attribution control renders the OSM source attribution.
            const instance = new maplibregl.Map({
                container: containerRef.current,
                style: OSM_STYLE,
                center: [start.lon, start.lat],
                zoom: initialCoords != null ? 15 : 12,
            });
            map = instance;
            mapRef.current = instance;

            instance.on('move', () => {
                const c = instance.getCenter();
                setCenter({ lat: c.lat, lon: c.lng });
            });

            instance.on('dragstart', () => {
                userInteracted = true;
            });
            instance.on('wheel', () => {
                userInteracted = true;
            });

            // A GPS fix that landed while the map was still loading: center it
            // now, unless the user beat the fix by panning manually.
            const pendingFix = pendingFixRef.current;
            if (pendingFix && !userInteracted) {
                pendingFixRef.current = null;
                instance.jumpTo({
                    center: [pendingFix.lon, pendingFix.lat],
                    zoom: 15,
                });
            }

            observer = new ResizeObserver(() => {
                instance.resize();
            });
            observer.observe(containerRef.current);
            setReady(true);
        })();

        return () => {
            cancelled = true;
            observer?.disconnect();
            mapRef.current = null;
            pendingFixRef.current = null;
            map?.remove();
            map = null;
            setReady(false);
            setGlUnsupported(false);
        };
        // Coordinates are a per-open snapshot; the map is rebuilt each open.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    function handleLocateMe() {
        if (locating) return;
        if (!('geolocation' in navigator)) {
            toast.error(
                'Browser tidak mendukung deteksi lokasi. Pastikan membuka lewat HTTPS dan browser terbaru.',
            );
            return;
        }
        setLocating(true);
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                setLocating(false);
                mapRef.current?.jumpTo({
                    center: [pos.coords.longitude, pos.coords.latitude],
                    zoom: 15,
                });
                haptic();
            },
            (err: GeolocationPositionError) => {
                setLocating(false);
                // Numeric codes: 1 PERMISSION_DENIED, 2 POSITION_UNAVAILABLE, 3 TIMEOUT
                // (the DOM lib types here don't expose the named constants).
                switch (err.code) {
                    case 1:
                        toast.error(
                            'Izin lokasi ditolak. Buka ikon kunci di address bar → Izinkan Lokasi → muat ulang halaman, lalu coba lagi.',
                        );
                        break;
                    case 2:
                        toast.error(
                            'Lokasi perangkat tidak terdeteksi. Pastikan GPS/layanan lokasi perangkat aktif, lalu coba lagi.',
                        );
                        break;
                    case 3:
                        toast.error(
                            'Waktu mencari lokasi habis. Coba lagi di tempat lebih terbuka.',
                        );
                        break;
                    default:
                        toast.error('Tidak bisa mengambil lokasi perangkat.');
                }
            },
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 30000 },
        );
    }

    function handleConfirm() {
        const map = mapRef.current;
        if (!map) return;
        const c = map.getCenter();
        haptic();
        onConfirm(c.lat, c.lng);
        onOpenChange(false);
    }

    return (
        <ResponsiveFormPanel
            open={open}
            onOpenChange={onOpenChange}
            title={title}
            description="Geser peta sampai titik tengah berada tepat di lokasi yang dimaksud."
            desktopWidthClass="data-[side=right]:sm:max-w-2xl"
            mobileContentClass="h-[90vh] max-h-[90vh]"
            autoFocusFirstField={false}
            footer={
                <div className="w-full">
                    <div className="mb-2 flex min-h-5 flex-wrap items-center justify-between gap-x-3 gap-y-1">
                        <span className="text-xs text-muted-foreground">
                            {center.lat.toFixed(5)}, {center.lon.toFixed(5)}
                        </span>
                    </div>
                    <div className="flex flex-col gap-2 sm:flex-row sm:justify-end">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={handleLocateMe}
                            disabled={!ready || locating}
                        >
                            <Crosshair className={locating ? 'animate-spin' : undefined} />
                            {locating ? 'Mencari…' : 'Lokasi Saya'}
                        </Button>
                        <Button type="button" onClick={handleConfirm} disabled={!ready}>
                            <MapPin />
                            Pakai Lokasi Ini
                        </Button>
                    </div>
                </div>
            }
        >
            <div className="relative overflow-hidden rounded-lg border">
                <div
                    ref={containerRef}
                    data-vaul-no-drag
                    className="h-[60vh] min-h-[320px] lg:h-[65vh]"
                />
                {/* Center crosshair: the confirmed location is always the map center. */}
                <div className="pointer-events-none absolute inset-0 z-10 flex items-center justify-center">
                    <div className="relative h-10 w-10 drop-shadow-[0_1px_2px_rgba(0,0,0,0.7)]">
                        <div className="absolute top-0 left-1/2 h-full w-0.5 -translate-x-1/2 bg-primary" />
                        <div className="absolute top-1/2 left-0 h-0.5 w-full -translate-y-1/2 bg-primary" />
                        <div className="absolute top-1/2 left-1/2 h-3 w-3 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-primary" />
                    </div>
                </div>
                {glUnsupported ? (
                    <div className="absolute inset-0 z-20 flex items-center justify-center bg-muted/60 p-6 text-center">
                        <span className="text-sm text-muted-foreground">
                            Browser atau perangkat ini tidak mendukung WebGL, sehingga peta
                            tidak bisa ditampilkan. Aktifkan akselerasi hardware di pengaturan
                            browser (atau perbarui driver GPU), lalu muat ulang — atau gunakan
                            browser/perangkat lain.
                        </span>
                    </div>
                ) : !ready && (
                    <div className="absolute inset-0 z-20 flex items-center justify-center bg-muted/60">
                        <span className="text-sm text-muted-foreground">Memuat peta…</span>
                    </div>
                )}
            </div>
        </ResponsiveFormPanel>
    );
}

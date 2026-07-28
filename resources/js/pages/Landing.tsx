import { useEffect, useRef, useState, type CSSProperties, type ReactNode } from 'react';
import { Link } from 'react-router-dom';
import {
    ArrowRight,
    ArrowUp,
    ArrowUpRight,
    BarChart3,
    Check,
    ClipboardList,
    Boxes,
    FileText,
    FileSpreadsheet,
    Monitor,
    Package,
    Settings,
    ShoppingCart,
    Smartphone,
    Users,
    Workflow,
    X,
} from 'lucide-react';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { getAppBranding } from '@/lib/branding';
import { cn } from '@/lib/utils';

const GITHUB_REPO_URL = 'https://github.com/piyuralabs/order-tracker'; // TODO: confirm
const WA_NUMBER = '6282257109095';
const WA_SUBSCRIBE_URL = `https://wa.me/${WA_NUMBER}?text=${encodeURIComponent(
    'Halo, saya ingin berlangganan Simple Order Systems Managed (Rp 29.000/bulan).',
)}`;

const EASE = 'ease-[cubic-bezier(0.32,0.72,0,1)]';
const REVEAL = 'transition-[transform,opacity,filter] duration-700 will-change-transform ' + EASE;

function Reveal({
    children,
    className,
    delay = 0,
}: {
    children: ReactNode;
    className?: string;
    delay?: number;
}) {
    const ref = useRef<HTMLDivElement>(null);
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        const el = ref.current;
        if (!el) return;
        if (typeof IntersectionObserver === 'undefined') {
            setVisible(true);
            return;
        }
        const obs = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        setVisible(true);
                        obs.unobserve(entry.target);
                    }
                });
            },
            { threshold: 0.12, rootMargin: '0px 0px -8% 0px' },
        );
        obs.observe(el);
        return () => obs.disconnect();
    }, []);

    return (
        <div
            ref={ref}
            data-reveal
            style={{ transitionDelay: `${delay}ms` }}
            className={cn(
                REVEAL,
                visible
                    ? 'translate-y-0 blur-0 opacity-100'
                    : 'translate-y-10 blur-md opacity-0',
                className,
            )}
        >
            {children}
        </div>
    );
}

function useMagnetic<T extends HTMLElement>(strength = 0.18) {
    const ref = useRef<T>(null);
    return {
        ref,
        onMouseMove(e: React.MouseEvent<T>) {
            const el = ref.current;
            if (!el) return;
            const r = el.getBoundingClientRect();
            const x = (e.clientX - (r.left + r.width / 2)) * strength;
            const y = (e.clientY - (r.top + r.height / 2)) * strength;
            el.style.transform = `translate3d(${x}px, ${y}px, 0)`;
        },
        onMouseLeave() {
            const el = ref.current;
            if (!el) return;
            el.style.transform = 'translate3d(0,0,0)';
        },
    };
}

function MagneticButton({
    children,
    href,
    to,
    tone = 'light',
    className,
}: {
    children: ReactNode;
    href?: string;
    to?: string;
    tone?: 'light' | 'dark' | 'outline';
    className?: string;
}) {
    const mag = useMagnetic<HTMLAnchorElement>();
    const base = cn(
        'landing-magnetic group/magnetic inline-flex h-12 items-center gap-2 rounded-full pl-6 pr-2 text-sm font-medium',
        'transition-transform duration-500 active:scale-[0.98] ' + EASE,
        tone === 'light' && 'bg-foreground text-background',
        tone === 'dark' && 'bg-background text-foreground',
        tone === 'outline' && 'border border-border bg-transparent text-foreground',
        className,
    );
    const inner = (
        <>
            <span className="flex items-center">{children}</span>
            <span
                className={cn(
                    'ml-1 flex size-8 items-center justify-center rounded-full',
                    'transition-transform duration-500 group-hover/magnetic:translate-x-0.5 group-hover/magnetic:-translate-y-px',
                    tone === 'light' ? 'bg-background/15' : 'bg-foreground/10',
                    EASE,
                )}
            >
                <ArrowRight className="size-4" data-icon="inline-end" aria-hidden />
            </span>
        </>
    );
    if (href) {
        return (
            <a
                ref={mag.ref as React.RefObject<HTMLAnchorElement>}
                href={href}
                target="_blank"
                rel="noopener noreferrer"
                onMouseMove={mag.onMouseMove as React.MouseEventHandler<HTMLAnchorElement>}
                onMouseLeave={mag.onMouseLeave}
                className={base}
            >
                {inner}
            </a>
        );
    }
    return (
        <Link
            ref={mag.ref as React.RefObject<HTMLAnchorElement>}
            to={to ?? '/login'}
            onMouseMove={mag.onMouseMove as React.MouseEventHandler<HTMLAnchorElement>}
            onMouseLeave={mag.onMouseLeave}
            className={base}
        >
            {inner}
        </Link>
    );
}

function Eyebrow({ children, tone = 'light' }: { children: ReactNode; tone?: 'light' | 'dark' }) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-2 rounded-full px-3 py-1 text-[10px] font-medium uppercase tracking-[0.2em]',
                tone === 'light'
                    ? 'border border-border bg-card text-muted-foreground'
                    : 'border border-white/15 bg-white/10 text-white/70',
            )}
        >
            {children}
        </span>
    );
}

const NAV_LINKS = [
    { href: '#showcase', label: 'Preview' },
    { href: '#fitur', label: 'Fitur' },
    { href: '#harga', label: 'Harga' },
] as const;

function Nav() {
    const [open, setOpen] = useState(false);

    useEffect(() => {
        if (!open) return;
        const prev = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') setOpen(false);
        };
        window.addEventListener('keydown', onKey);
        return () => {
            document.body.style.overflow = prev;
            window.removeEventListener('keydown', onKey);
        };
    }, [open]);

    return (
        <>
            <header className="pointer-events-none fixed inset-x-0 top-0 z-40 px-4 pt-5 md:pt-6">
                <nav className="pointer-events-auto mx-auto flex w-max max-w-[calc(100vw-2rem)] items-center gap-1.5 rounded-full border border-black/[0.06] bg-white/75 p-1.5 pl-5 shadow-[0_12px_40px_-20px_rgba(26,24,20,0.4)] backdrop-blur-2xl">
                    <Link
                        to="/"
                        className="flex items-center gap-2 pr-1 text-sm font-semibold tracking-tight text-foreground"
                        onClick={() => setOpen(false)}
                    >
                        <span className="flex size-7 items-center justify-center rounded-full bg-foreground text-background">
                            <ShoppingCart className="size-4" aria-hidden />
                        </span>
                        <span className="font-heading hidden sm:inline">Simple Order Systems</span>
                        <span className="font-heading sm:hidden">SOS</span>
                    </Link>
                    <Separator orientation="vertical" className="mx-1 h-6 bg-border" />
                    <Button
                        variant="ghost"
                        size="sm"
                        render={<Link to="/login" />}
                        className="rounded-full text-muted-foreground hover:bg-foreground/5 hover:text-foreground"
                    >
                        Masuk
                    </Button>
                    <button
                        type="button"
                        aria-label={open ? 'Tutup menu' : 'Buka menu'}
                        aria-expanded={open}
                        aria-controls="landing-nav-menu"
                        onClick={() => setOpen((v) => !v)}
                        className="relative flex size-9 shrink-0 items-center justify-center rounded-full bg-foreground text-background transition-transform duration-500 active:scale-[0.96]"
                        style={{ transitionTimingFunction: 'cubic-bezier(0.32,0.72,0,1)' }}
                    >
                        <span className="relative block size-3.5" aria-hidden>
                            <span
                                className={cn(
                                    'absolute left-0 top-[4px] block h-[1.5px] w-full rounded-full bg-background transition-transform duration-500',
                                    open && 'top-[6.5px] rotate-45',
                                )}
                                style={{ transitionTimingFunction: 'cubic-bezier(0.32,0.72,0,1)' }}
                            />
                            <span
                                className={cn(
                                    'absolute left-0 top-[9px] block h-[1.5px] w-full rounded-full bg-background transition-transform duration-500',
                                    open && 'top-[6.5px] -rotate-45',
                                )}
                                style={{ transitionTimingFunction: 'cubic-bezier(0.32,0.72,0,1)' }}
                            />
                        </span>
                    </button>
                </nav>
            </header>

            <div
                id="landing-nav-menu"
                role="dialog"
                aria-modal="true"
                aria-hidden={!open}
                className={cn(
                    'fixed inset-0 z-30 flex flex-col items-center justify-center gap-10 bg-white/85 px-6 backdrop-blur-3xl transition-[opacity,visibility] duration-500',
                    open ? 'visible opacity-100' : 'invisible opacity-0 pointer-events-none',
                )}
                style={{ transitionTimingFunction: 'cubic-bezier(0.32,0.72,0,1)' }}
            >
                <ul className="flex flex-col items-center gap-5 text-center">
                    {NAV_LINKS.map((link, i) => (
                        <li
                            key={link.href}
                            className={cn(
                                'transition-[transform,opacity] duration-700',
                                open ? 'translate-y-0 opacity-100' : 'translate-y-10 opacity-0',
                            )}
                            style={{
                                transitionDelay: open ? `${120 + i * 70}ms` : '0ms',
                                transitionTimingFunction: 'cubic-bezier(0.32,0.72,0,1)',
                            }}
                        >
                            <a
                                href={link.href}
                                onClick={() => setOpen(false)}
                                className="font-serif text-4xl font-semibold tracking-tight text-foreground transition-opacity duration-500 hover:opacity-60 md:text-5xl"
                                style={{ color: 'var(--landing-ink)' }}
                            >
                                {link.label}
                            </a>
                        </li>
                    ))}
                </ul>
                <div
                    className={cn(
                        'flex flex-col items-center gap-3 transition-[transform,opacity] duration-700 sm:flex-row',
                        open ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0',
                    )}
                    style={{
                        transitionDelay: open ? '340ms' : '0ms',
                        transitionTimingFunction: 'cubic-bezier(0.32,0.72,0,1)',
                    }}
                >
                    <MagneticButton href={GITHUB_REPO_URL} tone="light">
                        Mulai Gratis
                    </MagneticButton>
                    <Button
                        variant="outline"
                        size="lg"
                        render={<Link to="/login" />}
                        onClick={() => setOpen(false)}
                        className="rounded-full"
                    >
                        Masuk
                    </Button>
                </div>
            </div>
        </>
    );
}

function HeroOrbs() {
    return (
        <div aria-hidden className="pointer-events-none absolute inset-0 -z-10 overflow-hidden">
            <div
                className="absolute left-[-8%] top-[-10%] size-[36rem] rounded-full blur-3xl"
                style={{
                    background: 'radial-gradient(circle, var(--landing-amber), transparent 68%)',
                    opacity: 0.28,
                }}
            />
            <div
                className="absolute right-[-6%] top-[18%] size-[32rem] rounded-full blur-3xl"
                style={{
                    background: 'radial-gradient(circle, var(--landing-sage), transparent 70%)',
                    opacity: 0.35,
                }}
            />
            <div
                className="absolute bottom-[-18%] left-[28%] size-[40rem] rounded-full blur-3xl"
                style={{
                    background: 'radial-gradient(circle, var(--landing-rose), transparent 72%)',
                    opacity: 0.12,
                }}
            />
        </div>
    );
}

type ShowcaseTab = 'pesanan' | 'katalog' | 'laporan' | 'pengaturan';
type ShowcaseDevice = 'desktop' | 'mobile';

const SHOWCASE_TABS: Array<{ id: ShowcaseTab; label: string; icon: typeof ClipboardList }> = [
    { id: 'pesanan', label: 'Pesanan', icon: ClipboardList },
    { id: 'katalog', label: 'Katalog', icon: Package },
    { id: 'laporan', label: 'Laporan', icon: BarChart3 },
    { id: 'pengaturan', label: 'Pengaturan', icon: Settings },
];

/** Auto from Vite hash of public/landing/*.png — rebuild after replacing shots. */
const LANDING_SHOT_V = __LANDING_SHOT_V__;

const SHOWCASE_IMAGES: Record<ShowcaseTab, { desktop: string; mobile: string; alt: string }> = {
    pesanan: {
        desktop: `/landing/pesanan.png?v=${LANDING_SHOT_V}`,
        mobile: `/landing/pesanan-mobile.png?v=${LANDING_SHOT_V}`,
        alt: 'Layar Pesanan — daftar order harian',
    },
    katalog: {
        desktop: `/landing/katalog.png?v=${LANDING_SHOT_V}`,
        mobile: `/landing/katalog-mobile.png?v=${LANDING_SHOT_V}`,
        alt: 'Layar Katalog — produk dan stok',
    },
    laporan: {
        desktop: `/landing/laporan.png?v=${LANDING_SHOT_V}`,
        mobile: `/landing/laporan-mobile.png?v=${LANDING_SHOT_V}`,
        alt: 'Layar Laporan — ringkasan penjualan',
    },
    pengaturan: {
        desktop: `/landing/pengaturan.png?v=${LANDING_SHOT_V}`,
        mobile: `/landing/pengaturan-mobile.png?v=${LANDING_SHOT_V}`,
        alt: 'Layar Pengaturan — profil toko',
    },
};

type HeroChipTone = 'ink' | 'amber' | 'emerald' | 'rose' | 'sage';

const HERO_CHIPS: Array<{
    id: string;
    title: string;
    subtitle: string;
    badge: string;
    mark: string;
    tone: HeroChipTone;
    delayClass: string;
    className: string;
}> = [
    {
        id: 'sales',
        title: 'Omzet hari ini',
        subtitle: 'Rp 86.580 · 1 pesanan',
        badge: 'Hari ini',
        mark: 'Rp',
        tone: 'emerald',
        delayClass: 'landing-float-chip-d2',
        // 50% - half copy (~11rem) - gap (2rem) - chip (~15rem)
        className:
            'hidden xl:flex top-[6%] w-[15rem] -rotate-2 left-[max(0.5rem,calc(50%-16rem-3rem-15rem))]',
    },
    {
        id: 'stock',
        title: 'Stok rendah',
        subtitle: 'Gula Pasir 1kg · sisa 8 pcs',
        badge: 'Alert',
        mark: '!',
        tone: 'amber',
        delayClass: 'landing-float-chip-d1',
        className:
            'hidden xl:flex top-[10%] w-[15rem] rotate-2 right-[max(0.5rem,calc(50%-16rem-3rem-15rem))]',
    },
    {
        id: 'order',
        title: 'Pesanan #1284',
        subtitle: 'Bu Rina · Diproses · Rp 86.580',
        badge: 'Live',
        mark: '#…',
        tone: 'ink',
        delayClass: '',
        className:
            'hidden xl:flex top-[38%] w-[15.5rem] -rotate-[2deg] left-[max(0.5rem,calc(50%-16rem-3.25rem-15.5rem))]',
    },
    {
        id: 'queue',
        title: '4 pesanan aktif',
        subtitle: '2 menunggu · 2 diproses',
        badge: 'Antrian',
        mark: '4',
        tone: 'rose',
        delayClass: 'landing-float-chip-d3',
        className:
            'hidden xl:flex top-[42%] w-[15rem] rotate-[2deg] right-[max(0.5rem,calc(50%-16rem-3.25rem-15rem))]',
    },
    {
        id: 'invoice',
        title: 'Invoice siap',
        subtitle: 'INV-1284 · PDF terunduh',
        badge: 'PDF',
        mark: '∑',
        tone: 'sage',
        delayClass: 'landing-float-chip-d4',
        className:
            'hidden xl:flex top-[70%] w-[14.5rem] rotate-[-1.5deg] left-[max(0.5rem,calc(50%-16rem-3rem-14.5rem))]',
    },
    {
        id: 'staff',
        title: 'Staf online',
        subtitle: 'Ary · Pak Budi · 2 aktif',
        badge: 'Tim',
        mark: '2',
        tone: 'ink',
        delayClass: 'landing-float-chip-d5',
        className:
            'hidden xl:flex top-[74%] w-[14.5rem] rotate-[1.5deg] right-[max(0.5rem,calc(50%-16rem-3rem-14.5rem))]',
    },
    {
        id: 'sync',
        title: 'Sheets tersinkron',
        subtitle: 'Terakhir · 2 menit lalu',
        badge: 'Sync',
        mark: '↻',
        tone: 'emerald',
        delayClass: 'landing-float-chip-d1',
        className:
            'hidden xl:flex left-1/2 top-full mt-14 w-[min(16rem,calc(100%-2rem))] -translate-x-1/2 md:mt-16 md:w-[15rem]',
    },
];

function chipMarkStyle(tone: HeroChipTone): CSSProperties {
    switch (tone) {
        case 'amber':
            return { background: 'color-mix(in oklab, var(--landing-amber) 85%, white)', color: 'var(--landing-ink)' };
        case 'emerald':
            return { background: 'color-mix(in oklab, var(--landing-emerald) 75%, white)', color: 'var(--landing-ink)' };
        case 'rose':
            return { background: 'color-mix(in oklab, var(--landing-rose) 70%, white)', color: 'var(--landing-ink)' };
        case 'sage':
            return { background: 'color-mix(in oklab, var(--landing-sage) 80%, white)', color: 'var(--landing-ink)' };
        default:
            return { background: 'var(--landing-ink)', color: 'var(--landing-cream)' };
    }
}

function FloatingChip({
    title,
    subtitle,
    badge,
    mark,
    tone,
    delayClass,
    className,
}: (typeof HERO_CHIPS)[number]) {
    return (
        <div className={cn('pointer-events-none absolute z-10', className)} aria-hidden>
            <div
                className={cn(
                    'landing-float-chip flex w-full items-center gap-3 rounded-2xl border border-black/[0.05] bg-white/90 px-3.5 py-3',
                    'shadow-[0_24px_50px_-28px_rgba(26,24,20,0.45),inset_0_1px_0_rgba(255,255,255,0.9)]',
                    delayClass,
                )}
            >
                <span
                    className="flex size-10 shrink-0 items-center justify-center rounded-xl text-xs font-semibold tracking-tight"
                    style={chipMarkStyle(tone)}
                >
                    {mark}
                </span>
                <div className="flex min-w-0 flex-1 flex-col gap-0.5 text-left">
                    <span className="truncate text-sm font-medium tracking-tight text-foreground">{title}</span>
                    <span className="truncate text-[11px] text-muted-foreground">{subtitle}</span>
                </div>
                <Badge variant="secondary" className="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-medium">
                    {badge}
                </Badge>
            </div>
        </div>
    );
}

function Hero() {
    return (
        <section
            className="relative overflow-hidden"
            style={{
                background:
                    'linear-gradient(165deg, var(--landing-cream) 0%, var(--landing-sand) 48%, var(--landing-cream) 100%)',
            }}
        >
            <HeroOrbs />
            <div
                aria-hidden
                className="pointer-events-none absolute inset-x-0 bottom-0 h-[28%]"
                style={{
                    background:
                        'linear-gradient(to top, var(--landing-cream) 0%, transparent 100%)',
                }}
            />
            <div className="relative mx-auto flex min-h-[100dvh] max-w-7xl items-center justify-center px-4 pb-36 pt-24 md:pb-40 md:pt-28">
                <div className="relative w-full pb-28 md:pb-32">
                    {/* Narrower copy so side gutters stay open for chips */}
                    <div className="relative z-20 mx-auto flex w-full max-w-sm flex-col items-center text-center sm:max-w-md md:max-w-lg">
                        <Reveal>
                            <p
                                className="font-serif text-lg font-medium tracking-tight sm:text-xl md:text-2xl"
                                style={{ color: 'var(--landing-ink)' }}
                            >
                                Simple Order Systems
                            </p>
                        </Reveal>
                        <Reveal delay={90}>
                            <h1
                                className="mt-6 font-serif text-4xl font-semibold leading-[1.08] tracking-tight sm:text-5xl md:text-6xl"
                                style={{ color: 'var(--landing-ink)' }}
                            >
                                Catatan jualan,
                                <br />
                                jadi sistem.
                            </h1>
                        </Reveal>
                        <Reveal delay={170}>
                            <p className="mt-6 max-w-md text-base leading-relaxed text-muted-foreground md:text-lg">
                                Sistem order sederhana. Bukan POS rumit. Gratis untuk 1 toko, tinggal pakai.
                            </p>
                        </Reveal>
                        <Reveal delay={250} className="mt-8 flex flex-col items-center gap-3 sm:flex-row">
                            <MagneticButton href={GITHUB_REPO_URL} tone="light">
                                Mulai Gratis
                            </MagneticButton>
                            <a
                                href="#showcase"
                                className="inline-flex h-12 items-center gap-2 rounded-full border border-border bg-white/50 px-6 text-sm font-medium text-foreground transition-colors duration-500 hover:bg-white"
                            >
                                Lihat Preview
                            </a>
                        </Reveal>
                    </div>

                    {HERO_CHIPS.map((chip) => (
                        <FloatingChip key={chip.id} {...chip} />
                    ))}
                </div>
            </div>
        </section>
    );
}

function StatStrip() {
    const stats: Array<{ value: string; label: string; note: string }> = [
        { value: '5–10', label: 'pesanan per hari', note: 'Skala toko harian' },
        { value: 'Rp 0', label: 'biaya SaaS', note: 'Self-host selamanya' },
        { value: '100%', label: 'data milikmu', note: 'Tanpa vendor lock' },
    ];

    return (
        <section className="mx-auto max-w-6xl px-4 py-20 md:py-28">
            <Reveal>
                <div className="rounded-[2rem] bg-black/[0.035] p-1.5 ring-1 ring-black/[0.05]">
                    <div
                        className="grid grid-cols-1 gap-px overflow-hidden rounded-[calc(2rem-0.375rem)] md:grid-cols-3"
                        style={{ background: 'color-mix(in oklab, var(--landing-sand) 70%, white)' }}
                    >
                        {stats.map((s, i) => (
                            <Reveal
                                key={s.label}
                                delay={i * 90}
                                className={cn(
                                    'relative flex flex-col gap-4 bg-[var(--landing-cream)] px-7 py-9 md:px-9 md:py-11',
                                    i === 1 && 'md:bg-white',
                                )}
                            >
                                <Badge
                                    variant="secondary"
                                    className="w-fit rounded-full px-2.5 py-0.5 text-[10px] font-medium uppercase tracking-[0.18em]"
                                >
                                    {s.note}
                                </Badge>
                                <div className="flex flex-col gap-2">
                                    <span
                                        className="font-serif text-5xl font-semibold tracking-tight md:text-6xl lg:text-7xl"
                                        style={{ color: 'var(--landing-ink)' }}
                                    >
                                        {s.value}
                                    </span>
                                    <span className="text-sm text-muted-foreground md:text-base">{s.label}</span>
                                </div>
                                <span
                                    aria-hidden
                                    className="pointer-events-none absolute bottom-5 right-6 font-serif text-6xl font-semibold leading-none opacity-[0.04] md:text-7xl"
                                    style={{ color: 'var(--landing-ink)' }}
                                >
                                    {String(i + 1).padStart(2, '0')}
                                </span>
                            </Reveal>
                        ))}
                    </div>
                </div>
            </Reveal>
        </section>
    );
}

function ProblemFix() {
    const pains: Array<{ label: string; hint: string }> = [
        { label: 'Buku catatan penuh & hilang', hint: 'Tidak bisa dicari' },
        { label: 'Sticky notes berceceran', hint: 'Etalase berantakan' },
        { label: 'Spreadsheet mudah salah', hint: 'Hitung ulang tiap malam' },
    ];
    const fixes: Array<{ label: string; hint: string }> = [
        { label: 'Satu dasbor semua pesanan', hint: 'Status jelas tiap order' },
        { label: 'Stok otomatis tiap transaksi', hint: 'Tanpa hitung manual' },
        { label: 'Owner & staf terkontrol', hint: 'Akses sesuai peran' },
    ];

    return (
        <section className="relative overflow-hidden py-28 md:py-36">
            <div
                aria-hidden
                className="pointer-events-none absolute inset-0 -z-10"
                style={{
                    background:
                        'linear-gradient(180deg, transparent 0%, color-mix(in oklab, var(--landing-sand) 55%, transparent) 40%, transparent 100%)',
                }}
            />

            <div className="mx-auto max-w-6xl px-4">
                <Reveal>
                    <div className="flex flex-col gap-6 md:max-w-2xl">
                        <Badge
                            variant="secondary"
                            className="w-fit rounded-full px-3 py-1 text-[10px] font-medium uppercase tracking-[0.2em]"
                        >
                            Apa yang diselesaikan?
                        </Badge>
                        <h2
                            className="font-serif text-4xl font-semibold leading-[1.08] tracking-tight md:text-5xl lg:text-6xl"
                            style={{ color: 'var(--landing-ink)' }}
                        >
                            Catatan manual,
                            <br />
                            jadi sistem rapi.
                        </h2>
                        <p className="max-w-lg text-base leading-relaxed text-muted-foreground md:text-lg">
                            Tinggalkan buku catatan dan spreadsheet. Satu alur pesanan terstruktur, bisa diaudit.
                        </p>
                    </div>
                </Reveal>

                <div className="mt-14 grid grid-cols-1 gap-6 md:mt-20 md:grid-cols-[1fr_auto_1fr] md:items-stretch md:gap-4 lg:gap-8">
                    {/* Sebelum */}
                    <Reveal delay={80}>
                        <div className="flex h-full flex-col gap-4 md:-rotate-1">
                            <div className="flex items-baseline justify-between gap-3 px-1">
                                <span className="font-serif text-2xl font-semibold tracking-tight text-muted-foreground md:text-3xl">
                                    Sebelum
                                </span>
                                <Badge variant="outline" className="rounded-full text-[10px] uppercase tracking-[0.16em]">
                                    Chaos
                                </Badge>
                            </div>
                            <div className="rounded-[2rem] bg-black/[0.04] p-1.5 ring-1 ring-black/[0.05]">
                                <ul className="flex flex-col gap-1 rounded-[calc(2rem-0.375rem)] bg-[color-mix(in_oklab,var(--landing-cream)_88%,white)] p-3 shadow-[inset_0_1px_0_rgba(255,255,255,0.85)]">
                                    {pains.map((p) => (
                                        <li
                                            key={p.label}
                                            className="flex items-start gap-3 rounded-2xl px-3 py-3.5"
                                        >
                                            <span className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full bg-destructive/10 text-destructive">
                                                <X className="stroke-[1.25]" aria-hidden />
                                            </span>
                                            <div className="flex min-w-0 flex-col gap-0.5">
                                                <span className="text-sm font-medium text-muted-foreground line-through decoration-destructive/35">
                                                    {p.label}
                                                </span>
                                                <span className="text-[11px] text-muted-foreground/70">{p.hint}</span>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        </div>
                    </Reveal>

                    {/* Bridge */}
                    <Reveal delay={140} className="hidden items-center justify-center md:flex">
                        <div
                            className="flex size-12 items-center justify-center rounded-full border border-border bg-white shadow-[0_18px_40px_-28px_rgba(26,24,20,0.45)]"
                            aria-hidden
                        >
                            <ArrowRight className="stroke-[1.25] text-foreground" />
                        </div>
                    </Reveal>
                    <div className="flex items-center justify-center md:hidden" aria-hidden>
                        <div className="flex size-10 rotate-90 items-center justify-center rounded-full border border-border bg-white">
                            <ArrowRight className="stroke-[1.25]" />
                        </div>
                    </div>

                    {/* Sesudah */}
                    <Reveal delay={200}>
                        <div className="flex h-full flex-col gap-4 md:rotate-1">
                            <div className="flex items-baseline justify-between gap-3 px-1">
                                <span
                                    className="font-serif text-2xl font-semibold tracking-tight md:text-3xl"
                                    style={{ color: 'var(--landing-ink)' }}
                                >
                                    Sesudah
                                </span>
                                <Badge className="rounded-full bg-foreground text-[10px] uppercase tracking-[0.16em] text-background">
                                    Rapi
                                </Badge>
                            </div>
                            <div className="rounded-[2rem] bg-[color-mix(in_oklab,var(--landing-ink)_8%,transparent)] p-1.5 ring-1 ring-black/[0.06]">
                                <ul className="flex flex-col gap-1 rounded-[calc(2rem-0.375rem)] bg-card p-3 shadow-[inset_0_1px_0_rgba(255,255,255,0.9)]">
                                    {fixes.map((f) => (
                                        <li
                                            key={f.label}
                                            className="flex items-start gap-3 rounded-2xl px-3 py-3.5"
                                        >
                                            <span
                                                className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full"
                                                style={{
                                                    background: 'var(--landing-ink)',
                                                    color: 'var(--landing-cream)',
                                                }}
                                            >
                                                <Check className="stroke-[1.25]" aria-hidden />
                                            </span>
                                            <div className="flex min-w-0 flex-col gap-0.5">
                                                <span className="text-sm font-medium text-foreground">{f.label}</span>
                                                <span className="text-[11px] text-muted-foreground">{f.hint}</span>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        </div>
                    </Reveal>
                </div>
            </div>
        </section>
    );
}

type Feature = {
    title: string;
    description: string;
    icon: typeof Workflow;
    span: string;
    chart: string;
};

const FEATURES: Feature[] = [
    {
        title: 'Alur pesanan jelas',
        description: 'Draft → Pending → Proses → Kirim → Selesai. Status selalu terlacak.',
        icon: Workflow,
        span: 'md:col-span-4',
        chart: 'landing-violet',
    },
    {
        title: 'Stok otomatis',
        description: 'Stok berkurang sendiri tiap pesanan, tanpa hitung manual.',
        icon: Boxes,
        span: 'md:col-span-2',
        chart: 'landing-emerald',
    },
    {
        title: 'Owner & Staf',
        description: 'Pisah akses pemilik dan staf, data tetap aman.',
        icon: Users,
        span: 'md:col-span-2',
        chart: 'landing-amber',
    },
    {
        title: 'Invoice + PPN',
        description: 'Cetak invoice rapi, hitung PPN otomatis.',
        icon: FileText,
        span: 'md:col-span-2',
        chart: 'landing-rose',
    },
    {
        title: 'Sync Google Sheets',
        description: 'Sinkron order langsung ke spreadsheet, tanpa n8n.',
        icon: FileSpreadsheet,
        span: 'md:col-span-3',
        chart: 'landing-violet',
    },
    {
        title: 'Self-host di VPS sendiri',
        description: 'Pasang di servermu sendiri, data penuh di tanganmu.',
        icon: Package,
        span: 'md:col-span-3',
        chart: 'landing-emerald',
    },
];

function Features() {
    return (
        <section id="fitur" className="mx-auto max-w-6xl px-4 py-24 md:py-28">
            <Reveal>
                <Eyebrow>Fitur Inti</Eyebrow>
            </Reveal>
            <Reveal delay={80}>
                <h2 className="mt-6 max-w-2xl font-serif text-3xl font-semibold leading-tight tracking-tight text-foreground md:text-4xl">
                    Semua yang dibutuhkan toko kecil.
                </h2>
            </Reveal>
            <div className="mt-12 grid grid-cols-1 gap-4 md:grid-cols-6">
                {FEATURES.map((f, i) => (
                    <Reveal key={f.title} delay={(i % 3) * 80} className={f.span}>
                        <Card
                            className={cn(
                                'h-full border bg-card p-6 shadow-none md:p-7',
                                'rounded-[1.75rem] ring-1 ring-black/[0.04]',
                            )}
                            style={{ borderColor: `var(--${f.chart})` } as React.CSSProperties}
                        >
                            <CardContent className="flex h-full flex-col gap-3 p-0">
                                <span
                                    className="flex size-10 items-center justify-center rounded-full"
                                    style={{ background: `color-mix(in oklch, var(--${f.chart}) 12%, transparent)` }}
                                >
                                    <f.icon
                                        className="size-5 stroke-[1.25]"
                                        style={{ color: `var(--${f.chart})` }}
                                        aria-hidden
                                    />
                                </span>
                                <h3 className="font-heading text-lg font-medium text-foreground">{f.title}</h3>
                                <p className="text-sm leading-relaxed text-muted-foreground">{f.description}</p>
                            </CardContent>
                        </Card>
                    </Reveal>
                ))}
            </div>
        </section>
    );
}

function ShowcaseFrame({
    tab,
    device,
}: {
    tab: ShowcaseTab;
    device: ShowcaseDevice;
}) {
    const shot = SHOWCASE_IMAGES[tab];
    const src = device === 'mobile' ? shot.mobile : shot.desktop;

    if (device === 'mobile') {
        return (
            <div className="mx-auto w-full max-w-[320px] sm:max-w-[360px]">
                <div className="rounded-[2rem] bg-foreground/[0.04] p-2 ring-1 ring-foreground/10 sm:rounded-[2.25rem] sm:p-2.5">
                    <div className="overflow-hidden rounded-[calc(2rem-0.5rem)] bg-card shadow-[inset_0_1px_0_rgba(255,255,255,0.5)] sm:rounded-[calc(2.25rem-0.625rem)]">
                        <div className="flex items-center justify-center bg-muted/40 py-2">
                            <span className="h-1.5 w-16 rounded-full bg-foreground/15" aria-hidden />
                        </div>
                        <img
                            key={src}
                            src={src}
                            alt={shot.alt}
                            className="landing-shot block h-auto w-full object-cover object-top"
                            loading="lazy"
                            decoding="async"
                        />
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className="w-full">
            <div className="rounded-[1.5rem] bg-foreground/[0.04] p-2 ring-1 ring-foreground/10 sm:rounded-[1.75rem] sm:p-2.5 md:rounded-[2rem] md:p-3">
                <div className="overflow-hidden rounded-[calc(1.5rem-0.5rem)] bg-card sm:rounded-[calc(1.75rem-0.625rem)] md:rounded-[calc(2rem-0.75rem)]">
                    <div className="flex items-center gap-1.5 border-b border-border/70 bg-muted/40 px-3 py-2.5 md:px-4">
                        <span className="size-2 rounded-full bg-foreground/15" aria-hidden />
                        <span className="size-2 rounded-full bg-foreground/15" aria-hidden />
                        <span className="size-2 rounded-full bg-foreground/15" aria-hidden />
                        <span className="ml-2 truncate text-[10px] text-muted-foreground md:text-[11px]">
                            app.simpleordersystems
                        </span>
                    </div>
                    <img
                        key={src}
                        src={src}
                        alt={shot.alt}
                        className="landing-shot block h-auto w-full object-cover object-top"
                        loading="lazy"
                        decoding="async"
                    />
                </div>
            </div>
        </div>
    );
}

function Showcase() {
    const [active, setActive] = useState<ShowcaseTab>('pesanan');
    const [device, setDevice] = useState<ShowcaseDevice>('desktop');

    return (
        <section id="showcase" className="mx-auto max-w-7xl px-4 py-24 md:py-28">
            <div className="flex flex-col gap-10 md:gap-12">
                <div className="flex flex-col gap-8 lg:flex-row lg:items-end lg:justify-between lg:gap-12">
                    <div className="flex max-w-xl flex-col gap-4">
                        <Reveal>
                            <Eyebrow>Preview Aplikasi</Eyebrow>
                        </Reveal>
                        <Reveal delay={80}>
                            <h2 className="font-serif text-3xl font-semibold leading-tight tracking-tight text-foreground md:text-4xl lg:text-5xl">
                                Menu yang dipakai staf setiap hari.
                            </h2>
                        </Reveal>
                        <Reveal delay={140}>
                            <p className="max-w-md text-base leading-relaxed text-muted-foreground">
                                Cuplikan layar asli — ganti tab dan lihat versi desktop atau mobile.
                            </p>
                        </Reveal>
                    </div>

                    <Reveal delay={180} className="flex flex-col gap-3 lg:items-end">
                        <div
                            className="inline-flex w-fit items-center gap-1 rounded-full border border-border bg-card p-1"
                            role="group"
                            aria-label="Pilih perangkat preview"
                        >
                            {(
                                [
                                    { id: 'desktop' as const, label: 'Desktop', icon: Monitor },
                                    { id: 'mobile' as const, label: 'Mobile', icon: Smartphone },
                                ] as const
                            ).map((opt) => {
                                const Icon = opt.icon;
                                const isActive = device === opt.id;
                                return (
                                    <button
                                        key={opt.id}
                                        type="button"
                                        onClick={() => setDevice(opt.id)}
                                        className={cn(
                                            'inline-flex items-center gap-2 rounded-full px-3.5 py-1.5 text-sm font-medium transition-all duration-500',
                                            EASE,
                                            isActive
                                                ? 'bg-foreground text-background'
                                                : 'text-muted-foreground hover:text-foreground',
                                        )}
                                        aria-pressed={isActive}
                                    >
                                        <Icon className="stroke-[1.25]" aria-hidden />
                                        {opt.label}
                                    </button>
                                );
                            })}
                        </div>
                        <div className="flex flex-wrap gap-2 lg:justify-end">
                            {SHOWCASE_TABS.map((tab) => {
                                const Icon = tab.icon;
                                const isActive = active === tab.id;
                                return (
                                    <button
                                        key={tab.id}
                                        type="button"
                                        onClick={() => setActive(tab.id)}
                                        className={cn(
                                            'inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-medium transition-all duration-500',
                                            EASE,
                                            isActive
                                                ? 'bg-foreground text-background'
                                                : 'border border-border bg-card text-muted-foreground hover:text-foreground',
                                        )}
                                        aria-pressed={isActive}
                                    >
                                        <Icon className="stroke-[1.25]" aria-hidden />
                                        {tab.label}
                                    </button>
                                );
                            })}
                        </div>
                    </Reveal>
                </div>

                <Reveal delay={120} className="w-full">
                    <ShowcaseFrame tab={active} device={device} />
                </Reveal>
            </div>
        </section>
    );
}

function Pricing() {
    return (
        <section id="harga" className="mx-auto max-w-6xl px-4 py-24 md:py-28">
            <Reveal>
                <Eyebrow>Harga</Eyebrow>
            </Reveal>
            <Reveal delay={80}>
                <h2 className="mt-6 max-w-2xl font-serif text-3xl font-semibold leading-tight tracking-tight text-foreground md:text-4xl">
                    Mulai gratis, naik kelas saat siap.
                </h2>
            </Reveal>
            <div className="mt-12 grid grid-cols-1 gap-6 md:grid-cols-2">
                <Reveal>
                    <Card className="h-full rounded-[2rem] border border-border bg-card p-7 shadow-none md:p-8">
                        <CardContent className="flex h-full flex-col gap-5 p-0">
                            <Badge variant="secondary" className="w-fit rounded-full">Gratis</Badge>
                            <h3 className="font-heading text-2xl font-semibold text-foreground">Self-host</h3>
                            <p className="text-sm text-muted-foreground">Untuk 1 toko yang ingin mengelola sendiri.</p>
                            <div className="flex items-end gap-1">
                                <span className="font-serif text-4xl font-semibold tracking-tight text-foreground">Rp 0</span>
                                <span className="pb-1 text-sm text-muted-foreground">/selamanya</span>
                            </div>
                            <ul className="flex flex-col gap-2.5 text-sm">
                                {['1 toko, semua fitur inti', 'Data milikmu, tanpa kadaluarsa', 'Self-host di VPS sendiri'].map((f) => (
                                    <li key={f} className="flex items-center gap-2.5">
                                        <Check className="size-4 stroke-[1.5] text-foreground" aria-hidden />
                                        <span className="text-muted-foreground">{f}</span>
                                    </li>
                                ))}
                            </ul>
                            <div className="mt-auto pt-2">
                                <MagneticButton href={GITHUB_REPO_URL} tone="outline" className="w-full">
                                    Unduh dari GitHub
                                </MagneticButton>
                            </div>
                        </CardContent>
                    </Card>
                </Reveal>
                <Reveal delay={120}>
                    <div className="h-full rounded-[2rem] bg-sidebar-primary p-7 text-sidebar-primary-foreground md:p-8">
                        <div className="flex h-full flex-col gap-5">
                            <span className="inline-flex w-fit items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-[10px] font-medium uppercase tracking-[0.2em]">
                                Berlangganan
                            </span>
                            <h3 className="font-heading text-2xl font-semibold">Managed</h3>
                            <p className="text-sm text-sidebar-primary-foreground/80">Kami host, setup, backup, dan support. Tinggal pakai.</p>
                            <div className="flex items-end gap-1">
                                <span className="font-serif text-4xl font-semibold tracking-tight">Rp 29.000</span>
                                <span className="pb-1 text-sm text-sidebar-primary-foreground/80">/bulan</span>
                            </div>
                            <ul className="flex flex-col gap-2.5 text-sm">
                                {['Kami hosting + setup awal', 'Backup otomatis harian', 'Dukungan dan pembaruan rutin'].map((f) => (
                                    <li key={f} className="flex items-center gap-2.5">
                                        <Check className="size-4 stroke-[1.5]" aria-hidden />
                                        <span className="text-sidebar-primary-foreground/90">{f}</span>
                                    </li>
                                ))}
                            </ul>
                            <div className="mt-auto pt-2">
                                <MagneticButton href={WA_SUBSCRIBE_URL} tone="dark" className="w-full bg-background text-foreground">
                                    Chat WhatsApp
                                </MagneticButton>
                            </div>
                        </div>
                    </div>
                </Reveal>
            </div>
            <Reveal delay={160}>
                <p className="mt-6 text-center text-xs text-muted-foreground">Add-on berbayar untuk versi cloud menyusul.</p>
            </Reveal>
        </section>
    );
}

function Footer({ platformName }: { platformName: string }) {
    return (
        <footer className="mx-auto max-w-6xl px-4 pb-16 pt-8">
            <Separator className="mb-8" />
            <div className="flex flex-col items-center justify-between gap-6 md:flex-row">
                <div className="flex items-center gap-2">
                    <Avatar className="size-8 rounded-full">
                        <AvatarFallback className="rounded-full bg-foreground text-xs font-semibold text-background">
                            P
                        </AvatarFallback>
                    </Avatar>
                    <div className="flex flex-col">
                        <span className="font-heading text-sm font-medium text-foreground">Simple Order Systems</span>
                        <span className="text-xs text-muted-foreground">Catatan jualan, jadi sistem.</span>
                    </div>
                </div>
                <div className="flex flex-col items-center gap-3 text-xs text-muted-foreground md:flex-row md:items-center">
                    <a
                        href={GITHUB_REPO_URL}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="inline-flex items-center gap-1 transition-colors hover:text-foreground"
                    >
                        GitHub
                        <ArrowUpRight className="size-3" aria-hidden />
                    </a>
                    <Separator orientation="vertical" className="hidden h-4 md:block" />
                    <span>Dibuat oleh {platformName}</span>
                </div>
            </div>
        </footer>
    );
}

function BackToTop() {
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        const onScroll = () => setVisible(window.scrollY > 480);
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
        return () => window.removeEventListener('scroll', onScroll);
    }, []);

    return (
        <button
            type="button"
            aria-label="Kembali ke atas"
            onClick={() => window.scrollTo({ top: 0, behavior: 'smooth' })}
            className={cn(
                'fixed bottom-6 right-4 z-40 flex size-11 items-center justify-center rounded-full',
                'bg-[var(--landing-ink)] text-[var(--landing-cream)]',
                'shadow-[0_18px_44px_-18px_rgba(26,24,20,0.55)] transition-[opacity,transform,visibility] duration-500',
                'hover:opacity-90 active:scale-[0.96] md:bottom-8 md:right-6',
                visible
                    ? 'visible translate-y-0 opacity-100'
                    : 'invisible translate-y-3 opacity-0 pointer-events-none',
            )}
            style={{ transitionTimingFunction: 'cubic-bezier(0.32,0.72,0,1)' }}
        >
            <ArrowUp className="stroke-[1.5]" aria-hidden />
        </button>
    );
}

export default function Landing() {
    const branding = getAppBranding();

    return (
        <div
            className="landing-grain relative min-h-dvh overflow-x-hidden text-foreground"
            style={{ background: 'var(--landing-cream)' }}
        >
            <Nav />
            <Hero />
            <main>
                <StatStrip />
                <ProblemFix />
                <Showcase />
                <Features />
                <Pricing />
            </main>
            <Footer platformName={branding.platform_name} />
            <BackToTop />
        </div>
    );
}

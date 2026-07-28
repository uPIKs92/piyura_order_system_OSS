import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { TriangleAlert } from 'lucide-react';
import { toast } from 'sonner';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Input } from '@/components/ui/input';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useApi } from '@/lib/ApiProvider';
import { getAppBranding, getLoginTenantBranding, safeLogoUrl } from '@/lib/branding';
import { APP_FRAME_CLASS } from '@/lib/layout';
import { api } from '@/lib/api';
import { haptic } from '@/lib/format';
import type { LoginTenantBranding } from '@/lib/branding';

function getLoginErrorMessage(err: unknown): string {
    if (!(err instanceof Error)) {
        return 'Login gagal. Silakan coba lagi.';
    }

    const message = err.message;

    if (message.includes('credentials are incorrect')) {
        return 'Email atau kata sandi salah. Silakan coba lagi.';
    }

    if (message.includes('deactivated')) {
        return 'Akun ini telah dinonaktifkan.';
    }

    if (message.includes('Too many login attempts')) {
        return 'Terlalu banyak percobaan login. Coba lagi dalam 1 menit.';
    }

    return 'Login gagal. Silakan coba lagi.';
}

export default function Login() {
    const { refreshUser } = useApi();
    const navigate = useNavigate();
    const cachedTenant = api.getCachedTenantBranding();
    const serverTenant = getLoginTenantBranding();
    const tenantBranding: LoginTenantBranding | null = serverTenant ?? cachedTenant;
    const appBranding = getAppBranding();
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [loading, setLoading] = useState(false);
    const [loginError, setLoginError] = useState<string | null>(null);

    async function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        setLoginError(null);
        setLoading(true);
        try {
            await api.login(email, password);
            await refreshUser();
            haptic();
            toast.success('Berhasil masuk');
            navigate('/orders');
        } catch (err) {
            haptic([50, 50, 50]);
            setLoginError(getLoginErrorMessage(err));
        } finally {
            setLoading(false);
        }
    }

    const tenantName = tenantBranding?.name ?? appBranding.app_name;
    const tenantInitial = tenantName.charAt(0)?.toUpperCase() ?? null;
    const tenantTagline =
        tenantBranding?.tagline?.trim() ||
        (tenantBranding ? 'Masuk untuk melanjutkan' : 'Masuk untuk mengelola pesanan');
    const tenantLogoUrl = safeLogoUrl(tenantBranding?.logo_url);

    return (
        <div className="flex min-h-dvh items-center justify-center bg-background">
            <div className={`${APP_FRAME_CLASS} w-full`}>
                <Card className="mx-auto w-full max-w-sm gap-3 border-0 bg-card shadow-none sm:border sm:shadow-sm">
                    <CardHeader className="flex flex-col items-center gap-3 text-center">
                        {tenantBranding ? (
                            <Avatar className="size-12 rounded-lg">
                                {tenantLogoUrl ? (
                                    <AvatarImage src={tenantLogoUrl} alt={tenantName} />
                                ) : null}
                                {tenantInitial ? (
                                    <AvatarFallback className="rounded-lg text-lg">{tenantInitial}</AvatarFallback>
                                ) : null}
                            </Avatar>
                        ) : null}
                        <div className="mb-4 flex flex-col gap-1">
                            <CardTitle className="text-2xl">{tenantName}</CardTitle>
                            <CardDescription>{tenantTagline}</CardDescription>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit}>
                            <FieldGroup>
                                {loginError ? (
                                    <div
                                        role="alert"
                                        className="rounded-lg border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive"
                                    >
                                        <div className="flex items-start gap-2">
                                            <TriangleAlert className="mt-0.5 size-4 shrink-0" aria-hidden />
                                            <span>{loginError}</span>
                                        </div>
                                    </div>
                                ) : null}
                                <Field>
                                    <FieldLabel htmlFor="email">Email</FieldLabel>
                                    <Input
                                        id="email"
                                        type="email"
                                        value={email}
                                        onChange={(e) => {
                                            setEmail(e.target.value);
                                            setLoginError(null);
                                        }}
                                        placeholder="email@contoh.com"
                                        required
                                        autoComplete="email"
                                    />
                                </Field>
                                <Field>
                                    <FieldLabel htmlFor="password">Kata sandi</FieldLabel>
                                    <Input
                                        id="password"
                                        type="password"
                                        value={password}
                                        onChange={(e) => {
                                            setPassword(e.target.value);
                                            setLoginError(null);
                                        }}
                                        placeholder="••••••••"
                                        required
                                        autoComplete="current-password"
                                    />
                                </Field>
                                <Button type="submit" className="w-full" disabled={loading}>
                                    {loading ? <Spinner data-icon="inline-start" /> : null}
                                    Masuk
                                </Button>
                                <div className="mt-4 flex flex-col items-center gap-2 text-center text-xs text-muted-foreground">
                                    <p>
                                        by {appBranding.platform_name} | {appBranding.app_name}
                                    </p>
                                </div>
                            </FieldGroup>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}

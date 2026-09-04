import { lazy, Suspense } from 'react';
import { Navigate, Route, Routes } from 'react-router-dom';
import { AppShell } from '@/components/AppShell';
import { OfflineFallback } from '@/components/OfflineFallback';
import { PageFallback } from '@/components/PageFallback';
import { useApi } from '@/lib/ApiProvider';

const Login = lazy(() => import('@/pages/Login'));
const Orders = lazy(() => import('@/pages/Orders'));
const Catalog = lazy(() => import('@/pages/Catalog'));
const Users = lazy(() => import('@/pages/Users'));
const ReportsDashboard = lazy(() => import('@/pages/reports/ReportsDashboard'));
const SettingsLayout = lazy(() => import('@/pages/settings/SettingsLayout'));
const SettingsProfile = lazy(() => import('@/pages/settings/SettingsProfile'));
const SettingsAppearance = lazy(() => import('@/pages/settings/SettingsAppearance'));
const SettingsOps = lazy(() => import('@/pages/settings/SettingsOps'));
const SettingsPayment = lazy(() => import('@/pages/settings/SettingsPayment'));

function ProtectedRoute({ children, roles }: { children: React.ReactNode; roles?: string[] }) {
    const { user, loading, offline } = useApi();
    if (loading) return <PageFallback />;
    if (!user) {
        // Session probe failed offline (user never loaded): offer a retry
        // instead of redirecting to /login, which would strand the user.
        if (offline) return <OfflineFallback />;
        return <Navigate to="/login" replace />;
    }
    if (roles && !roles.includes(user.role)) return <Navigate to="/orders" replace />;
    return <>{children}</>;
}

export default function App() {
    const { user, loading, offline } = useApi();

    return (
        <Suspense fallback={<PageFallback />}>
            <Routes>
                <Route
                    path="/login"
                    element={!loading && user ? <Navigate to="/orders" replace /> : <Login />}
                />
                <Route
                    path="/"
                    element={
                        loading ? (
                            <PageFallback />
                        ) : user ? (
                            <Navigate to="/orders" replace />
                        ) : offline ? (
                            <OfflineFallback />
                        ) : (
                            <Navigate to="/login" replace />
                        )
                    }
                />
                <Route element={<AppShell />}>
                    <Route path="/orders" element={<Orders />} />
                    <Route
                        path="/settings"
                        element={
                            <ProtectedRoute roles={['owner']}>
                                <SettingsLayout />
                            </ProtectedRoute>
                        }
                    >
                        <Route index element={<SettingsProfile />} />
                        <Route path="appearance" element={<SettingsAppearance />} />
                        <Route path="ops" element={<SettingsOps />} />
                        <Route path="payment" element={<SettingsPayment />} />
                    </Route>
                    <Route path="/reports/ops" element={<Navigate to="/settings/ops" replace />} />
                    <Route path="/products" element={<Navigate to="/catalog?tab=produk" replace />} />
                    <Route path="/categories" element={<Navigate to="/catalog?tab=kategori" replace />} />
                    <Route
                        path="/catalog"
                        element={
                            <ProtectedRoute roles={['owner']}>
                                <Catalog />
                            </ProtectedRoute>
                        }
                    />
                    <Route
                        path="/users"
                        element={
                            <ProtectedRoute roles={['owner']}>
                                <Users />
                            </ProtectedRoute>
                        }
                    />
                    <Route
                        path="/reports"
                        element={
                            <ProtectedRoute roles={['owner']}>
                                <ReportsDashboard />
                            </ProtectedRoute>
                        }
                    />
                </Route>
                <Route path="*" element={<Navigate to="/orders" replace />} />
            </Routes>
        </Suspense>
    );
}

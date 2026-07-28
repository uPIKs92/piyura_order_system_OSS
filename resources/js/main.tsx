import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import { ApiProvider } from '@/lib/ApiProvider';
import { ThemeProvider } from '@/components/ThemeProvider';
import { TenantThemeSync } from '@/components/TenantThemeSync';
import App from '@/App';
import '../css/app.css';

const root = document.getElementById('root');
if (root) {
    createRoot(root).render(
        <BrowserRouter>
            <ThemeProvider>
                <ApiProvider>
                    <TenantThemeSync />
                    <App />
                </ApiProvider>
            </ThemeProvider>
        </BrowserRouter>
    );
}

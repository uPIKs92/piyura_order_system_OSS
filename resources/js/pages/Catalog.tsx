import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { IosPageHeader } from '@/components/ios/IosPageHeader';
import { Page } from '@/components/Page';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { CategoriesPanel } from '@/pages/catalog/CategoriesPanel';
import { ProductsPanel } from '@/pages/catalog/ProductsPanel';
import { StockPanel } from '@/pages/catalog/StockPanel';
import { useInventoryAlerts } from '@/hooks/use-inventory-alerts';

type CatalogTab = 'produk' | 'kategori' | 'stok';

function isCatalogTab(value: string | null): value is CatalogTab {
    return value === 'produk' || value === 'kategori' || value === 'stok';
}

export default function Catalog() {
    const [searchParams, setSearchParams] = useSearchParams();
    const tabParam = searchParams.get('tab');
    const [tab, setTab] = useState<CatalogTab>(
        tabParam === 'terima' ? 'stok' : isCatalogTab(tabParam) ? tabParam : 'produk',
    );
    const { refresh } = useInventoryAlerts();

    useEffect(() => {
        if (tabParam === 'terima') {
            setSearchParams({ tab: 'stok' }, { replace: true });
            setTab('stok');
            return;
        }
        if (isCatalogTab(tabParam) && tabParam !== tab) {
            setTab(tabParam);
        }
    }, [tabParam, tab, setSearchParams]);

    function handleTabChange(value: string) {
        if (!isCatalogTab(value)) return;
        setTab(value);
        setSearchParams({ tab: value }, { replace: true });
    }

    return (
        <Page>
            <IosPageHeader title="Katalog" description="Kelola produk, kategori, dan stok" />

            <Tabs value={tab} onValueChange={handleTabChange} className="flex flex-col gap-4">
                <TabsList variant="segment" className="flex-wrap">
                    <TabsTrigger value="produk">Produk</TabsTrigger>
                    <TabsTrigger value="kategori">Kategori</TabsTrigger>
                    <TabsTrigger value="stok">Stok</TabsTrigger>
                </TabsList>

                <TabsContent value="produk">
                    <ProductsPanel onInventoryChange={refresh} />
                </TabsContent>

                <TabsContent value="kategori">
                    <CategoriesPanel />
                </TabsContent>

                <TabsContent value="stok">
                    <StockPanel onInventoryChange={refresh} />
                </TabsContent>
            </Tabs>
        </Page>
    );
}

import { useEffect, useRef, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { Plus } from 'lucide-react';
import { IosPageHeader } from '@/components/ios/IosPageHeader';
import { Page } from '@/components/Page';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { CategoriesPanel, type CategoriesPanelHandle } from '@/pages/catalog/CategoriesPanel';
import { ProductsPanel, type ProductsPanelHandle } from '@/pages/catalog/ProductsPanel';
import { StockPanel } from '@/pages/catalog/StockPanel';
import { SuppliersPanel, type SuppliersPanelHandle } from '@/pages/catalog/SuppliersPanel';
import { StockOpnamePanel } from '@/pages/catalog/StockOpnamePanel';
import { useInventoryAlerts } from '@/hooks/use-inventory-alerts';

type CatalogTab = 'produk' | 'kategori' | 'stok' | 'pemasok' | 'opname';

function isCatalogTab(value: string | null): value is CatalogTab {
    return (
        value === 'produk' ||
        value === 'kategori' ||
        value === 'stok' ||
        value === 'pemasok' ||
        value === 'opname'
    );
}

export default function Catalog() {
    const [searchParams, setSearchParams] = useSearchParams();
    const tabParam = searchParams.get('tab');
    const [tab, setTab] = useState<CatalogTab>(
        tabParam === 'terima' ? 'stok' : isCatalogTab(tabParam) ? tabParam : 'produk',
    );
    const { refresh } = useInventoryAlerts();
    const productsRef = useRef<ProductsPanelHandle>(null);
    const categoriesRef = useRef<CategoriesPanelHandle>(null);
    const suppliersRef = useRef<SuppliersPanelHandle>(null);

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
            <IosPageHeader
                title="Katalog"
                description="Kelola produk, kategori, dan stok"
                action={
                    tab === 'produk' ? (
                        <Button size="sm" onClick={() => productsRef.current?.openCreate()}>
                            <Plus data-icon="inline-start" />
                            Tambah
                        </Button>
                    ) : tab === 'kategori' ? (
                        <Button size="sm" onClick={() => categoriesRef.current?.openCreate()}>
                            <Plus data-icon="inline-start" />
                            Tambah
                        </Button>
                    ) : tab === 'pemasok' ? (
                        <Button size="sm" onClick={() => suppliersRef.current?.openCreate()}>
                            <Plus data-icon="inline-start" />
                            Tambah
                        </Button>
                    ) : null
                }
            />

            <Tabs value={tab} onValueChange={handleTabChange} className="flex flex-col gap-4">
                <TabsList variant="segment">
                    <TabsTrigger value="produk">Produk</TabsTrigger>
                    <TabsTrigger value="kategori">Kategori</TabsTrigger>
                    <TabsTrigger value="stok">Stok</TabsTrigger>
                    <TabsTrigger value="pemasok">Pemasok</TabsTrigger>
                    <TabsTrigger value="opname">Opname</TabsTrigger>
                </TabsList>

                <TabsContent value="produk">
                    <ProductsPanel ref={productsRef} onInventoryChange={refresh} />
                </TabsContent>

                <TabsContent value="kategori">
                    <CategoriesPanel ref={categoriesRef} />
                </TabsContent>

                <TabsContent value="stok">
                    <StockPanel onInventoryChange={refresh} />
                </TabsContent>

                <TabsContent value="pemasok">
                    <SuppliersPanel ref={suppliersRef} />
                </TabsContent>

                <TabsContent value="opname">
                    <StockOpnamePanel onInventoryChange={refresh} />
                </TabsContent>
            </Tabs>
        </Page>
    );
}

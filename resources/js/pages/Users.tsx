import { useEffect, useRef, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { Plus } from 'lucide-react';
import { IosPageHeader } from '@/components/ios/IosPageHeader';
import { Page } from '@/components/Page';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { StaffPanel, type StaffPanelHandle } from '@/pages/users/StaffPanel';
import { CustomersPanel, type CustomersPanelHandle } from '@/pages/users/CustomersPanel';

type UsersTab = 'staff' | 'pelanggan';

function isUsersTab(value: string | null): value is UsersTab {
    return value === 'staff' || value === 'pelanggan';
}

export default function Users() {
    const [searchParams, setSearchParams] = useSearchParams();
    const tabParam = searchParams.get('tab');
    const [tab, setTab] = useState<UsersTab>(isUsersTab(tabParam) ? tabParam : 'staff');
    const staffRef = useRef<StaffPanelHandle>(null);
    const customersRef = useRef<CustomersPanelHandle>(null);

    useEffect(() => {
        if (isUsersTab(tabParam) && tabParam !== tab) {
            setTab(tabParam);
        }
    }, [tabParam, tab]);

    function handleTabChange(value: string) {
        if (!isUsersTab(value)) return;
        setTab(value);
        setSearchParams({ tab: value }, { replace: true });
    }

    return (
        <Page>
            <IosPageHeader
                title="Pengguna"
                description="Kelola staff dan pelanggan"
                action={
                    <Button
                        size="sm"
                        onClick={() =>
                            tab === 'staff'
                                ? staffRef.current?.openCreate()
                                : customersRef.current?.openCreate()
                        }
                    >
                        <Plus data-icon="inline-start" />
                        Tambah
                    </Button>
                }
            />

            <Tabs value={tab} onValueChange={handleTabChange} className="flex flex-col gap-4">
                <TabsList variant="segment">
                    <TabsTrigger value="staff">Staff</TabsTrigger>
                    <TabsTrigger value="pelanggan">Pelanggan</TabsTrigger>
                </TabsList>

                <TabsContent value="staff">
                    <StaffPanel ref={staffRef} />
                </TabsContent>

                <TabsContent value="pelanggan">
                    <CustomersPanel ref={customersRef} />
                </TabsContent>
            </Tabs>
        </Page>
    );
}

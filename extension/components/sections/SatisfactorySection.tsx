import React, { useState } from 'react';
import classNames from 'classnames';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import FlashMessageRender from '@/components/FlashMessageRender';
import Spinner from '@/components/elements/Spinner';
import { ServerContext } from '@/state/server';
import { useStatus } from '../api/sfmanager';
import { t } from '../lang';
import { FLASH_KEY } from '../elements/ui';
import StatusCard from '../elements/StatusCard';
import ClaimWizard from '../elements/ClaimWizard';
import LoginCard from '../elements/LoginCard';
import ConnectionCard from '../elements/ConnectionCard';
import NewGameCard from '../elements/NewGameCard';
import SavesTab from '../elements/SavesTab';
import SettingsTab from '../elements/SettingsTab';
import ConsoleTab from '../elements/ConsoleTab';

type Tab = 'overview' | 'saves' | 'settings' | 'console';

const SatisfactorySection = () => {
  const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
  const serverName = ServerContext.useStoreState((state) => state.server.data!.name);
  const { data: status, revalidate } = useStatus(uuid);
  const [tab, setTab] = useState<Tab>('overview');

  const refresh = () => {
    revalidate();
  };

  // Savegames, settings and console need admin rights on the Satisfactory server.
  const isAdmin = !!status && status.reachable && (status.access === 'admin' || status.access === 'initial');
  const current: Tab = isAdmin ? tab : 'overview';
  const tabs: Tab[] = isAdmin ? ['overview', 'saves', 'settings', 'console'] : ['overview'];

  return (
    <ServerContentBlock title={t('title')}>
      <FlashMessageRender byKey={FLASH_KEY} className={'mb-4'} />
      {tabs.length > 1 && (
        <div className={'flex gap-1 mb-4 border-b border-neutral-600'}>
          {tabs.map((id) => (
            <button
              key={id}
              onClick={() => setTab(id)}
              className={classNames(
                'px-4 py-2 text-sm uppercase -mb-px border-b-2 transition-colors',
                current === id
                  ? 'border-cyan-400 text-neutral-50'
                  : 'border-transparent text-neutral-400 hover:text-neutral-200'
              )}
            >
              {t(`tab.${id}` as any)}
            </button>
          ))}
        </div>
      )}
      {!status ? (
        <Spinner centered size={'large'} />
      ) : current === 'saves' ? (
        <SavesTab uuid={uuid} onChange={refresh} />
      ) : current === 'settings' ? (
        <SettingsTab uuid={uuid} onChange={refresh} />
      ) : current === 'console' ? (
        <ConsoleTab uuid={uuid} />
      ) : (
        <div className={'grid lg:grid-cols-3 gap-4'}>
          <div className={'lg:col-span-2 space-y-4'}>
            <StatusCard uuid={uuid} status={status} onChange={refresh} />
            {status.reachable && status.access === 'initial' && (
              <ClaimWizard uuid={uuid} defaultName={serverName} onDone={refresh} />
            )}
            {status.reachable && (status.access === 'none' || status.access === 'client') && (
              <LoginCard uuid={uuid} onDone={refresh} />
            )}
            {status.reachable && status.access === 'admin' && (
              <NewGameCard uuid={uuid} defaultName={serverName} onDone={refresh} />
            )}
          </div>
          <div>
            <ConnectionCard uuid={uuid} />
          </div>
        </div>
      )}
    </ServerContentBlock>
  );
};

export default SatisfactorySection;

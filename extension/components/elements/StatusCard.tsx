import React, { useState } from 'react';
import classNames from 'classnames';
import { faServer } from '@fortawesome/free-solid-svg-icons';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import { Button } from '@/components/elements/button/index';
import { Status, logout, saveGame } from '../api/sfmanager';
import { t } from '../lang';
import { Dot, Stat, formatDuration, formatPhase, useNotify } from './ui';

interface Props {
  uuid: string;
  status: Status;
  onChange: () => void;
}

const tickClass = (tps: number) => (tps >= 25 ? 'text-green-400' : tps >= 15 ? 'text-yellow-400' : 'text-red-400');

const defaultSaveName = (session: string) => {
  const d = new Date();
  const pad = (n: number) => String(n).padStart(2, '0');
  const stamp = `${d.getFullYear()}${pad(d.getMonth() + 1)}${pad(d.getDate())}-${pad(d.getHours())}${pad(d.getMinutes())}`;
  return `${(session || 'save').replace(/[^\w -]/g, '')}_manual_${stamp}`;
};

export default ({ uuid, status, onChange }: Props) => {
  const notify = useNotify();
  const [busy, setBusy] = useState(false);
  const state = status.state;
  const canAdmin = status.access === 'admin' || status.access === 'initial';

  const doSave = () => {
    if (!state) return;
    const saveName = defaultSaveName(state.activeSessionName);
    setBusy(true);
    notify.clear();
    saveGame(uuid, saveName)
      .then(() => notify.success(t('save.done', saveName)))
      .catch(notify.error)
      .then(() => setBusy(false));
  };

  const doLogout = () => {
    logout(uuid).then(onChange).catch(notify.error);
  };

  const header = (
    <div className={'flex items-center justify-between'}>
      <p className={'text-sm uppercase flex items-center'}>
        <Dot ok={status.reachable} />
        {t('status.title')} · {status.reachable ? t('status.online') : t('status.offline')}
      </p>
      {status.access === 'admin' && (
        <span className={'text-xs text-neutral-400'}>
          {t('status.access.admin')} ·{' '}
          <button className={'underline hover:text-neutral-200'} onClick={doLogout}>
            {t('status.logout')}
          </button>
        </span>
      )}
      {status.access === 'client' && <span className={'text-xs text-neutral-400'}>{t('status.access.client')}</span>}
    </div>
  );

  return (
    <TitledGreyBox title={header} icon={faServer}>
      {!status.reachable && <p className={'text-neutral-300'}>{t('status.offlineHint')}</p>}
      {status.reachable && !state && <p className={'text-neutral-300'}>{t('status.hidden')}</p>}
      {status.reachable && state && (
        <>
          <div className={'grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3'}>
            <Stat label={t('status.players')} value={`${state.numConnectedPlayers} / ${state.playerLimit}`} />
            <Stat label={t('status.tier')} value={state.isGameRunning ? state.techTier : '–'} />
            <Stat label={t('status.phase')} value={state.isGameRunning ? formatPhase(state.gamePhase) : '–'} />
            <Stat
              label={t('status.tickrate')}
              value={state.averageTickRate.toFixed(1)}
              className={tickClass(state.averageTickRate)}
            />
            <Stat label={t('status.playtime')} value={formatDuration(state.totalGameDuration)} />
            <Stat
              label={t('status.session')}
              value={
                state.isGameRunning ? (
                  <>
                    {state.activeSessionName}
                    {state.isGamePaused && (
                      <span className={'text-xs text-neutral-400 ml-1'}>({t('status.paused')})</span>
                    )}
                  </>
                ) : (
                  t('status.noGame')
                )
              }
            />
          </div>
          {canAdmin && (
            <div className={classNames('flex justify-end mt-4')}>
              <Button onClick={doSave} disabled={busy || !state.isGameRunning}>
                {t('save.button')}
              </Button>
            </div>
          )}
        </>
      )}
    </TitledGreyBox>
  );
};

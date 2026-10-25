import React, { useEffect, useRef, useState } from 'react';
import axios from 'axios';
import { faSave, faUpload } from '@fortawesome/free-solid-svg-icons';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import Spinner from '@/components/elements/Spinner';
import { Button } from '@/components/elements/button/index';
import { Dialog } from '@/components/elements/dialog';
import getFileUploadUrl from '@/api/server/files/getFileUploadUrl';
import getFileDownloadUrl from '@/api/server/files/getFileDownloadUrl';
import {
  SaveHeader,
  Sessions,
  deleteSave,
  deleteSession,
  getSessions,
  loadSave,
  saveGame,
  setAutoload,
} from '../api/sfmanager';
import { t } from '../lang';
import { Badge, defaultSaveName, formatDuration, formatSaveDate, useNotify } from './ui';

type Pending =
  | { kind: 'load'; save: SaveHeader }
  | { kind: 'delete'; save: SaveHeader }
  | { kind: 'deleteSession'; session: string; count: number }
  | null;

export default ({ uuid, onChange }: { uuid: string; onChange: () => void }) => {
  const notify = useNotify();
  const [data, setData] = useState<Sessions | null>(null);
  const [busy, setBusy] = useState(false);
  const [pending, setPending] = useState<Pending>(null);
  const [saveFirst, setSaveFirst] = useState(true);
  const [advanced, setAdvanced] = useState(false);
  const [loadAfterUpload, setLoadAfterUpload] = useState(false);
  const [progress, setProgress] = useState<{ file: string; percent: number } | null>(null);
  const fileInput = useRef<HTMLInputElement>(null);

  const refresh = () =>
    getSessions(uuid)
      .then(setData)
      .catch(notify.error);

  useEffect(() => {
    refresh();
  }, [uuid]);

  // Runs an action, shows the result and reloads the list.
  const run = (action: () => Promise<any>, success: string) => {
    setBusy(true);
    notify.clear();
    return action()
      .then(() => {
        notify.success(success);
        onChange();
      })
      .catch(notify.error)
      .then(() => {
        setBusy(false);
        setPending(null);
        refresh();
      });
  };

  const download = (save: SaveHeader) => {
    getFileDownloadUrl(uuid, `/${data!.save_dir}/${save.saveName}.sav`)
      .then((url) => {
        window.location.href = url;
      })
      .catch(notify.error);
  };

  const upload = (file: File) => {
    notify.clear();
    if (!file.name.toLowerCase().endsWith('.sav')) {
      notify.errorText(t('saves.notSav'));
      return;
    }
    const saveName = file.name.replace(/\.sav$/i, '');
    setProgress({ file: file.name, percent: 0 });

    getFileUploadUrl(uuid)
      .then((url) =>
        axios.post(
          url,
          { files: file },
          {
            headers: { 'Content-Type': 'multipart/form-data' },
            params: { directory: `/${data!.save_dir}` },
            onUploadProgress: (e) =>
              setProgress({ file: file.name, percent: e.total ? Math.round((e.loaded / e.total) * 100) : 0 }),
          }
        )
      )
      .then(() => {
        notify.success(t('saves.uploaded', file.name));
        if (!loadAfterUpload) return undefined;
        return loadSave(uuid, saveName, false).then(() => {
          notify.success(t('saves.loading', saveName));
          onChange();
        });
      })
      .catch(notify.error)
      .then(() => {
        setProgress(null);
        if (fileInput.current) fileInput.current.value = '';
        refresh();
      });
  };

  const confirmPending = () => {
    if (!pending) return;
    if (pending.kind === 'load') {
      const save = pending.save;
      const current = data?.current_session;
      run(async () => {
        if (saveFirst && current) await saveGame(uuid, defaultSaveName(current, 'before-load'));
        await loadSave(uuid, save.saveName, advanced);
      }, t('saves.loading', save.saveName));
    } else if (pending.kind === 'delete') {
      run(() => deleteSave(uuid, pending.save.saveName), t('saves.deleted', pending.save.saveName));
    } else {
      run(() => deleteSession(uuid, pending.session), t('saves.deleted', pending.session));
    }
  };

  const openLoad = (save: SaveHeader) => {
    setAdvanced(save.isCreativeModeEnabled);
    setSaveFirst(!!data?.current_session);
    setPending({ kind: 'load', save });
  };

  return (
    <div className={'space-y-4'}>
      <TitledGreyBox title={t('saves.upload')} icon={faUpload}>
        <p className={'text-sm text-neutral-300 mb-3'}>{t('saves.uploadHint')}</p>
        <div className={'flex flex-wrap items-center gap-4'}>
          <input
            ref={fileInput}
            type={'file'}
            accept={'.sav'}
            className={'hidden'}
            onChange={(e) => e.currentTarget.files?.[0] && upload(e.currentTarget.files[0])}
          />
          <Button onClick={() => fileInput.current?.click()} disabled={!data || !!progress}>
            {t('saves.upload')}
          </Button>
          <label className={'flex items-center text-sm cursor-pointer'}>
            <input
              type={'checkbox'}
              className={'mr-2'}
              checked={loadAfterUpload}
              onChange={(e) => setLoadAfterUpload(e.currentTarget.checked)}
            />
            {t('saves.uploadLoad')}
          </label>
          {progress && (
            <span className={'text-sm text-neutral-300'}>{t('saves.uploading', progress.file, progress.percent)}</span>
          )}
        </div>
      </TitledGreyBox>

      {!data ? (
        <Spinner centered size={'large'} />
      ) : data.sessions.length === 0 ? (
        <p className={'text-neutral-400 text-center py-6'}>{t('saves.empty')}</p>
      ) : (
        data.sessions.map((session) => (
          <TitledGreyBox
            key={session.sessionName}
            icon={faSave}
            title={
              <div className={'flex flex-wrap items-center justify-between gap-2'}>
                <p className={'text-sm uppercase flex items-center'}>
                  {session.sessionName}
                  {data.current_session === session.sessionName && (
                    <Badge className={'bg-green-600'}>{t('saves.current')}</Badge>
                  )}
                  {data.autoload_session === session.sessionName && (
                    <Badge className={'bg-blue-600'}>{t('saves.autoload')}</Badge>
                  )}
                </p>
                <div className={'flex gap-2'}>
                  {data.autoload_session !== session.sessionName && (
                    <Button.Text
                      size={Button.Sizes.Small}
                      disabled={busy}
                      onClick={() =>
                        run(() => setAutoload(uuid, session.sessionName), t('saves.autoloadSet', session.sessionName))
                      }
                    >
                      {t('saves.setAutoload')}
                    </Button.Text>
                  )}
                  <Button.Danger
                    size={Button.Sizes.Small}
                    disabled={busy || data.current_session === session.sessionName}
                    onClick={() =>
                      setPending({ kind: 'deleteSession', session: session.sessionName, count: session.saves.length })
                    }
                  >
                    {t('saves.deleteSession')}
                  </Button.Danger>
                </div>
              </div>
            }
          >
            <div className={'divide-y divide-neutral-600'}>
              {session.saves.map((save) => (
                <div key={save.saveName} className={'flex flex-wrap items-center justify-between gap-2 py-2'}>
                  <div className={'min-w-0'}>
                    <p className={'font-mono text-sm truncate'}>
                      {save.saveName}
                      {save.isModdedSave && <Badge>{t('saves.modded')}</Badge>}
                      {save.isEditedSave && <Badge>{t('saves.edited')}</Badge>}
                      {save.isCreativeModeEnabled && <Badge className={'bg-yellow-600'}>{t('saves.creative')}</Badge>}
                    </p>
                    <p className={'text-xs text-neutral-400'}>
                      {formatSaveDate(save.saveDateTime)} · {t('saves.playtime')}{' '}
                      {formatDuration(save.playDurationSeconds)} · {t('saves.build')} {save.buildVersion}
                    </p>
                  </div>
                  <div className={'flex gap-2'}>
                    <Button.Text size={Button.Sizes.Small} onClick={() => download(save)}>
                      {t('saves.download')}
                    </Button.Text>
                    <Button size={Button.Sizes.Small} disabled={busy} onClick={() => openLoad(save)}>
                      {t('saves.load')}
                    </Button>
                    <Button.Danger
                      size={Button.Sizes.Small}
                      disabled={busy}
                      onClick={() => setPending({ kind: 'delete', save })}
                    >
                      {t('common.delete')}
                    </Button.Danger>
                  </div>
                </div>
              ))}
            </div>
          </TitledGreyBox>
        ))
      )}

      <Dialog.Confirm
        open={!!pending}
        onClose={() => setPending(null)}
        title={pending?.kind === 'load' ? t('saves.load') : t('common.delete')}
        confirm={pending?.kind === 'load' ? t('saves.load') : t('common.delete')}
        onConfirmed={confirmPending}
      >
        {pending?.kind === 'load' && (
          <div className={'space-y-3'}>
            <p>{t('saves.confirmLoad', pending.save.saveName)}</p>
            {data?.current_session && (
              <label className={'flex items-center text-sm cursor-pointer'}>
                <input
                  type={'checkbox'}
                  className={'mr-2'}
                  checked={saveFirst}
                  onChange={(e) => setSaveFirst(e.currentTarget.checked)}
                />
                {t('saves.saveFirst')}
              </label>
            )}
            <label className={'flex items-center text-sm cursor-pointer'}>
              <input
                type={'checkbox'}
                className={'mr-2'}
                checked={advanced}
                onChange={(e) => setAdvanced(e.currentTarget.checked)}
              />
              {t('saves.enableAdvanced')}
            </label>
          </div>
        )}
        {pending?.kind === 'delete' && <p>{t('saves.confirmDelete', pending.save.saveName)}</p>}
        {pending?.kind === 'deleteSession' && <p>{t('saves.confirmDeleteSession', pending.session, pending.count)}</p>}
      </Dialog.Confirm>
    </div>
  );
};

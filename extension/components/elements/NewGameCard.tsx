import React, { useState } from 'react';
import { faPlay } from '@fortawesome/free-solid-svg-icons';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import { Button } from '@/components/elements/button/index';
import { NewGame, STARTING_LOCATIONS, newGame } from '../api/sfmanager';
import { t } from '../lang';
import { useNotify } from './ui';
import NewGameFields from './NewGameFields';

export default ({ uuid, defaultName, onDone }: { uuid: string; defaultName: string; onDone: () => void }) => {
  const notify = useNotify();
  const [busy, setBusy] = useState(false);
  const [game, setGame] = useState<NewGame>({
    session_name: defaultName,
    starting_location: STARTING_LOCATIONS[0],
    skip_onboarding: false,
  });

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    setBusy(true);
    notify.clear();
    newGame(uuid, game)
      .then(() => {
        notify.success(t('newgame.done', game.session_name));
        onDone();
      })
      .catch(notify.error)
      .then(() => setBusy(false));
  };

  return (
    <TitledGreyBox title={t('newgame.title')} icon={faPlay}>
      <form onSubmit={submit}>
        <p className={'text-sm text-neutral-300 mb-4'}>{t('newgame.intro')}</p>
        <NewGameFields value={game} onChange={setGame} />
        <div className={'flex justify-end'}>
          <Button type={'submit'} disabled={busy || !game.session_name}>
            {busy ? '…' : t('newgame.submit')}
          </Button>
        </div>
      </form>
    </TitledGreyBox>
  );
};

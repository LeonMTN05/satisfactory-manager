import React from 'react';
import Input from '@/components/elements/Input';
import Select from '@/components/elements/Select';
import { NewGame, STARTING_LOCATIONS } from '../api/sfmanager';
import { t } from '../lang';
import { Field } from './ui';

/** Session name, starting location and onboarding switch – used by the claim wizard and the new game card. */
export default ({ value, onChange }: { value: NewGame; onChange: (v: NewGame) => void }) => (
  <div className={'grid md:grid-cols-2 gap-x-4'}>
    <Field label={t('claim.sessionName')}>
      <Input
        value={value.session_name}
        maxLength={64}
        onChange={(e) => onChange({ ...value, session_name: e.currentTarget.value })}
      />
    </Field>
    <Field label={t('claim.startingLocation')}>
      <Select
        value={value.starting_location}
        onChange={(e) => onChange({ ...value, starting_location: e.currentTarget.value })}
      >
        {STARTING_LOCATIONS.map((l) => (
          <option key={l} value={l}>
            {l}
          </option>
        ))}
      </Select>
    </Field>
    <label className={'flex items-center text-sm cursor-pointer mb-4'}>
      <input
        type={'checkbox'}
        className={'mr-2'}
        checked={value.skip_onboarding}
        onChange={(e) => onChange({ ...value, skip_onboarding: e.currentTarget.checked })}
      />
      {t('claim.skipOnboarding')}
    </label>
  </div>
);

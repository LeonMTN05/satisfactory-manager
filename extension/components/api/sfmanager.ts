import http from '@/api/http';
import useSWR from 'swr';

export interface GameState {
  activeSessionName: string;
  numConnectedPlayers: number;
  playerLimit: number;
  techTier: number;
  activeSchematic: string;
  gamePhase: string;
  isGameRunning: boolean;
  totalGameDuration: number;
  isGamePaused: boolean;
  averageTickRate: number;
  autoLoadSessionName: string;
}

export type Access = 'admin' | 'initial' | 'client' | 'none';

export interface Status {
  reachable: boolean;
  claimed: boolean | null;
  access: Access;
  state: GameState | null;
}

export interface Connection {
  address: string;
  port: number;
  reliable_port: number;
  reliable_port_allocated: boolean;
  checks: { game_tcp: boolean; reliable_tcp: boolean };
}

export interface ClaimData {
  server_name: string;
  admin_password: string;
  client_password?: string;
  new_game?: NewGame;
}

export const STARTING_LOCATIONS = ['Grass Fields', 'Rocky Desert', 'Northern Forest', 'Dune Desert'];

const base = (uuid: string) => `/api/client/extensions/sfmanager/servers/${uuid}`;

export const useStatus = (uuid: string) =>
  useSWR<Status>(['sfmanager:status', uuid], () => http.get(`${base(uuid)}/status`).then((r) => r.data), {
    refreshInterval: 10000,
  });

export const getConnection = (uuid: string): Promise<Connection> =>
  http.get(`${base(uuid)}/connection`).then((r) => r.data);

export interface NewGame {
  session_name: string;
  starting_location: string;
  skip_onboarding: boolean;
}

/** Resolves with error codes of follow-up steps that failed (e.g. new game). */
export const claimServer = (uuid: string, data: ClaimData): Promise<string[]> =>
  http.post(`${base(uuid)}/claim`, data).then((r) => r.data.warnings || []);

export const newGame = (uuid: string, data: NewGame) => http.post(`${base(uuid)}/new-game`, data);

export const login = (uuid: string, password: string) => http.post(`${base(uuid)}/login`, { password });

export const logout = (uuid: string) => http.delete(`${base(uuid)}/login`);

export const saveGame = (uuid: string, saveName: string) => http.post(`${base(uuid)}/save`, { name: saveName });

/** Error code from our backend (e.g. "wrong_password", "sfm_unreachable"). */
export const errorCode = (error: any): string | undefined => error?.response?.data?.errors?.[0]?.code;

import React, { useEffect, useState } from 'react';
import classNames from 'classnames';
import { faChartLine } from '@fortawesome/free-solid-svg-icons';
import { Line } from 'react-chartjs-2';
import {
  Chart as ChartJS,
  ChartOptions,
  Filler,
  Legend,
  LinearScale,
  LineElement,
  PointElement,
  Tooltip,
} from 'chart.js';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import Spinner from '@/components/elements/Spinner';
import { MetricPoint, getMetrics } from '../api/sfmanager';
import { t } from '../lang';

ChartJS.register(LineElement, PointElement, LinearScale, Tooltip, Legend, Filler);

const RANGES = ['24h', '7d', '30d'];
const PLAYERS = '#22d3ee';
const TICKRATE = '#fbbf24';

const formatTime = (seconds: number, range: string) => {
  const d = new Date(seconds * 1000);
  return range === '24h'
    ? d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
    : d.toLocaleDateString([], { day: '2-digit', month: '2-digit' }) +
        (range === '7d' ? ' ' + d.toLocaleTimeString([], { hour: '2-digit' }) : '');
};

export default ({ uuid }: { uuid: string }) => {
  const [range, setRange] = useState('24h');
  const [points, setPoints] = useState<MetricPoint[] | null>(null);

  useEffect(() => {
    setPoints(null);
    const load = () =>
      getMetrics(uuid, range)
        .then((r) => setPoints(r.points))
        .catch(() => setPoints([]));
    load();
    const timer = setInterval(load, 60000);
    return () => clearInterval(timer);
  }, [uuid, range]);

  const options: ChartOptions<'line'> = {
    responsive: true,
    maintainAspectRatio: false,
    animation: false,
    interaction: { mode: 'index', intersect: false },
    plugins: {
      legend: { labels: { color: '#cad1d8', boxWidth: 12 } },
      tooltip: { callbacks: { title: (items) => formatTime(items[0].parsed.x, range) } },
    },
    scales: {
      x: {
        type: 'linear',
        ticks: { color: '#8a939b', maxTicksLimit: 8, callback: (v) => formatTime(Number(v), range) },
        grid: { color: 'rgba(255,255,255,0.05)' },
      },
      y: {
        position: 'left',
        beginAtZero: true,
        ticks: { color: PLAYERS, precision: 0 },
        grid: { color: 'rgba(255,255,255,0.05)' },
      },
      y1: {
        position: 'right',
        min: 0,
        suggestedMax: 30,
        ticks: { color: TICKRATE },
        grid: { display: false },
      },
    },
  };

  const header = (
    <div className={'flex items-center justify-between'}>
      <p className={'text-sm uppercase'}>{t('history.title')}</p>
      <div className={'flex gap-1'}>
        {RANGES.map((r) => (
          <button
            key={r}
            onClick={() => setRange(r)}
            className={classNames(
              'text-xs px-2 py-1 rounded',
              r === range ? 'bg-neutral-600 text-neutral-50' : 'text-neutral-400 hover:text-neutral-200'
            )}
          >
            {r}
          </button>
        ))}
      </div>
    </div>
  );

  return (
    <TitledGreyBox title={header} icon={faChartLine}>
      {!points ? (
        <Spinner centered size={'small'} />
      ) : points.length === 0 ? (
        <p className={'text-sm text-neutral-400 py-6 text-center'}>{t('history.empty')}</p>
      ) : (
        <div className={'h-56'}>
          <Line
            options={options}
            data={{
              datasets: [
                {
                  label: t('history.players'),
                  data: points.map((p) => ({ x: p.t, y: p.players as number })),
                  borderColor: PLAYERS,
                  backgroundColor: 'rgba(34,211,238,0.15)',
                  fill: true,
                  stepped: true,
                  pointRadius: 0,
                  borderWidth: 2,
                  yAxisID: 'y',
                  spanGaps: false,
                },
                {
                  label: t('history.tickrate'),
                  data: points.map((p) => ({ x: p.t, y: p.tick_rate as number })),
                  borderColor: TICKRATE,
                  pointRadius: 0,
                  borderWidth: 1.5,
                  tension: 0.3,
                  yAxisID: 'y1',
                  spanGaps: false,
                },
              ],
            }}
          />
        </div>
      )}
    </TitledGreyBox>
  );
};

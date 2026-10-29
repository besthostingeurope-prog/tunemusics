import {appQueries} from '@app/app-queries';
import {useRadioRecommendations} from '@app/web-player/radio/use-radio-recommendations';
import {RadioRecommendationTrack} from '@app/web-player/radio/radio-recommendations-response';
import {PageMetaTags} from '@common/http/page-meta-tags';
import {Trans} from '@ui/i18n/trans';
import {IllustratedMessage} from '@ui/images/illustrated-message';
import {RadioIcon} from '@ui/icons/material/Radio';
import {useQuery} from '@tanstack/react-query';
import {apiClient} from '@common/http/query-client';
import {TrackTable} from '@app/web-player/tracks/track-table/track-table';
import {PlaybackToggleButton} from '@app/web-player/playable-item/playback-toggle-button';
import {actionButtonClassName, MediaPageHeaderLayout} from '@app/web-player/layout/media-page-header-layout';
import {BulletSeparatedItems} from '@app/web-player/layout/bullet-separated-items';
import {FormattedDuration} from '@ui/i18n/formatted-duration';
import {ProgressCircle} from '@ui/progress/progress-circle';
import {useMemo} from 'react';
import {Track} from '@app/web-player/tracks/track';

interface RadioLikeItem {
  type: 'artist' | 'genre' | 'track';
  seed: any;
}

interface RadioLikesResponse {
  radios: RadioLikeItem[];
}

export function Component() {
  const {data: likesData, isLoading: isLoadingLikes} = useQuery({
    queryKey: ['radio', 'likes'],
    queryFn: () => apiClient.get<RadioLikesResponse>('radio/likes').then(r => r.data),
  });

  const firstRadio = likesData?.radios?.[0];
  const seedType = firstRadio?.type || 'genre';
  const seedId = firstRadio?.seed?.id || 1;

  const {data: recsData, isLoading: isLoadingRecs} = useQuery({
    queryKey: ['radio', seedType, seedId],
    queryFn: () => apiClient.get(`radio/${seedType}/${seedId}`).then(r => r.data),
    enabled: !!likesData?.radios?.length,
  });

  const tracks: Track[] = useMemo(() => {
    return (recsData?.recommendations || []) as Track[];
  }, [recsData]);

  const totalDuration = useMemo(() => {
    return tracks.reduce((total, track) => total + (track.duration || 0), 0);
  }, [tracks]);

  if (isLoadingLikes) {
    return (
      <div className="flex h-full min-h-300 items-center justify-center">
        <ProgressCircle isIndeterminate size="md" />
      </div>
    );
  }

  const hasLikes = !!likesData?.radios?.length;

  return (
    <div className="p-16 md:p-24">
      <PageMetaTags title="My Radio - TuneMusics" />

      {!hasLikes ? (
        <div className="py-64 text-center">
          <IllustratedMessage
            image={<RadioIcon className="text-muted w-64 h-64 mx-auto mb-16" />}
            title={<Trans message="No radio stations saved yet" />}
            description={
              <Trans message="Start a radio from any artist, track, or genre to see your personalized stations here." />
            }
          />
        </div>
      ) : (
        <div>
          <MediaPageHeaderLayout
            image={
              <div className="flex h-192 w-192 items-center justify-center rounded-panel bg-primary/10 text-primary">
                <RadioIcon size="xl" />
              </div>
            }
            title={<Trans message="My Radio" />}
            subtitle={
              <BulletSeparatedItems className="justify-center text-sm text-muted md:justify-start">
                <span><Trans message="Personalized station" /></span>
                <Trans
                  message="[one 1 song|other :count songs]"
                  values={{count: tracks.length}}
                />
                <FormattedDuration ms={totalDuration} verbose />
              </BulletSeparatedItems>
            }
            actionsBar={
              tracks.length ? (
                <div className="text-center md:text-start">
                  <PlaybackToggleButton
                    tracks={tracks}
                    buttonType="text"
                    queueId="my-radio"
                    className={actionButtonClassName({isFirst: true})}
                  />
                </div>
              ) : null
            }
          />

          <div className="mt-24">
            <TrackTable tracks={tracks} queueGroupId="my-radio" />
          </div>
        </div>
      )}
    </div>
  );
}

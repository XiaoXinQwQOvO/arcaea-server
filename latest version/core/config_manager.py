class Config:
    '''
    Default config
    '''

    HOST = '0.0.0.0'
    PORT = 80

    DEPLOY_MODE = 'flask_multithread'
    USE_PROXY_FIX = False
    USE_CORS = False

    SONG_FILE_HASH_PRE_CALCULATE = True

    GAME_API_PREFIX = ['/brieflywingtip/41', '/']  # str | list[str]
    OLD_GAME_API_PREFIX = []  # str | list[str]

    ALLOW_APPVERSION = []  # list[str]

    BUNDLE_STRICT_MODE = True

    SET_LINKPLAY_SERVER_AS_SUB_PROCESS = True

    LINKPLAY_HOST = '0.0.0.0'
    LINKPLAY_UDP_PORT = 10900
    LINKPLAY_TCP_PORT = 10901
    LINKPLAY_AUTHENTICATION = 'my_link_play_server'
    LINKPLAY_DISPLAY_HOST = ''
    LINKPLAY_TCP_SECRET_KEY = '1145141919810'

    SSL_CERT = ''
    SSL_KEY = ''

    IS_APRILFOOLS = True

    ENABLE_WORLD_RANK = True
    WORLD_RANK_MAX = 200

    AVAILABLE_MAP = []  # list[str]

    USERNAME = 'admin'
    PASSWORD = 'admin'

    SECRET_KEY = '1145141919810'

    API_TOKEN = ''

    DOWNLOAD_LINK_PREFIX = ''  # http(s)://host(:port)/download/
    BUNDLE_DOWNLOAD_LINK_PREFIX = ''  # http(s)://host(:port)/bundle_download/ 填写后直接拼接该前缀+文件路径下载，不生成 token、不走本地服务器下载；留空则走本地 token 下载

    DOWNLOAD_USE_NGINX_X_ACCEL_REDIRECT = False
    NGINX_X_ACCEL_REDIRECT_PREFIX = '/nginx_download/'
    BUNDLE_NGINX_X_ACCEL_REDIRECT_PREFIX = '/nginx_bundle_download/'
    REMOTE_DOWNLOAD_MODE = 'localhost'
    REMOTE_DOWNLOAD_OPTIONS = {
        'nginx': {
            # nginx secure_link
            'download_link_host': 'http://please.change.this.to.your.domain:port',
            'download_link_prefix': '/arcaea_server_download',
            'bundle_download_link_host': 'http://please.change.this.to.your.domain:port',
            'bundle_download_link_prefix': '/arcaea_server_bundle_download',
            'secret_key': 'nginx_secure_link_md5_secret_key',
        },
        's3': {
            'endpoint_url': 'https://s3.your-cloud.com',
            'aws_access_key_id': 'your_aws_access_key_id',
            'aws_secret_access_key': 'your_aws_secret_access_key',
            'config': {
                'signature_version': 's3v4',
                's3': {
                    'addressing_style': 'path'
                }
            },
            'region_name': 'us-east-1',
            'song_bucket_name': 'your_bucket_name',
            'bundle_bucket_name': 'your_bundle_bucket_name_which_can_be_same_as_bucket_name',
            'song_file_key_prefix': 'songs/',
            'bundle_file_key_prefix': 'bundles/',
        }
    }

    DOWNLOAD_TIMES_LIMIT = 3000
    DOWNLOAD_TIME_GAP_LIMIT = 1000

    DOWNLOAD_FORBID_WHEN_NO_ITEM = False

    # 是否把所有歌曲都当作需要从服务器下载的远端歌曲
    # 官方客户端自带歌曲文件，所以 songlist 里没标 remote_dl 的歌曲只下发 BYD 谱面；
    # 私有服务器分发的客户端通常不带歌曲文件，此时需要下发全部谱面与音频，否则客户端会报「下载歌曲时发生问题」
    # If all songs should be treated as remote download songs
    # The official client bundles song files, so only the BYD chart is served for songs without `remote_dl` in songlist.
    # Clients distributed by private servers usually have no bundled songs, so all charts and audios should be served.
    DOWNLOAD_FORCE_REMOTE = False

    BUNDLE_DOWNLOAD_TIMES_LIMIT = '1000/60 minutes'
    BUNDLE_DOWNLOAD_TIME_GAP_LIMIT = 3000

    LOGIN_DEVICE_NUMBER_LIMIT = 1
    ALLOW_LOGIN_SAME_DEVICE = False
    ALLOW_BAN_MULTIDEVICE_USER_AUTO = True

    ALLOW_SCORE_WITH_NO_SONG = True

    # 是否关闭成绩有效性校验
    # 为 True 时不校验分数、谱面 hash 和提交 hash，允许所有成绩上传
    # If score validation is disabled
    # If `True`, score, chart hash and submission hash will not be verified, all scores are allowed to be uploaded.
    ALLOW_ALL_SCORE = False

    ALLOW_INFO_LOG = False
    ALLOW_WARNING_LOG = False

    DEFAULT_MEMORIES = 0

    UPDATE_WITH_NEW_CHARACTER_DATA = True

    CHARACTER_FULL_UNLOCK = True
    WORLD_SONG_FULL_UNLOCK = True
    WORLD_SONG_FULL_UNLOCK_WITHOUT_MAP = False
    WORLD_SCENERY_FULL_UNLOCK = True
    ONLINE_BANNER_FULL_UNLOCK = True

    SAVE_FULL_UNLOCK = False

    ALLOW_SELF_ACCOUNT_DELETE = False

    DEPLOY_OPTIONS = {
        'gunicorn': {
            'bind': f'{HOST}:{PORT}',
            'worker_class': 'sync',
            'workers': 1,
            'threads': 4,
            'timeout': 30,
            'certfile': SSL_CERT or None,
            'keyfile': SSL_KEY or None,
        },
    }

    # ------------------------------------------

    # You can change this to make another PTT mechanism.
    # BEST30_WEIGHT = 1 / 40
    # RECENT10_WEIGHT = 1 / 40
    # BEST10_WEIGHT = 0
    # BEST50_WEIGHT = 0
    # CLEAR_BONUS = 0.0
    BEST30_WEIGHT = 0
    RECENT10_WEIGHT = 0
    BEST10_WEIGHT = 1 / 60
    BEST50_WEIGHT = 1 / 60
    CLEAR_BONUS = 0.2

    INVASION_START_WEIGHT = 0.1
    INVASION_HARD_WEIGHT = 0.1

    MAX_FRIEND_COUNT = 50

    WORLD_MAP_FOLDER_PATH = './database/map/'
    SONG_FILE_FOLDER_PATH = './database/songs/'
    SONGLIST_FILE_PATH = './database/songs/songlist'
    CONTENT_BUNDLE_FOLDER_PATH = './database/bundle/'
    SQLITE_DATABASE_PATH = './database/arcaea_database.db'
    SQLITE_DATABASE_BACKUP_FOLDER_PATH = './database/backup/'
    DATABASE_INIT_PATH = './database/init/'
    SQLITE_LOG_DATABASE_PATH = './database/arcaea_log.db'
    SQLITE_DATABASE_DELETED_PATH = './database/arcaea_database_deleted.db'
    LOG_FOLDER_PATH = './log/'

    GAME_LOGIN_RATE_LIMIT = '30/5 minutes'
    API_LOGIN_RATE_LIMIT = '10/5 minutes'
    GAME_REGISTER_IP_RATE_LIMIT = '10/1 day'
    GAME_REGISTER_DEVICE_RATE_LIMIT = '3/1 day'

    NOTIFICATION_EXPIRE_TIME = 3 * 60 * 1000


class ConfigManager:

    @staticmethod
    def load(config) -> None:
        for k, v in config.__dict__.items():
            if k.startswith('__') or k.endswith('__'):
                continue
            if hasattr(Config, k):
                setattr(Config, k, v)

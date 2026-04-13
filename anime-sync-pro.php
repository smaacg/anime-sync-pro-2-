    // 6-4. 後台 + Cron 環境：初始化核心元件
    if ( is_admin() || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {

        // 建立共用核心物件（順序與 constructor 簽名一致）
        $rate_limiter   = class_exists( 'Anime_Sync_Rate_Limiter' )
                          ? new Anime_Sync_Rate_Limiter()
                          : null;

        $id_mapper      = class_exists( 'Anime_Sync_ID_Mapper' )
                          ? new Anime_Sync_ID_Mapper( $rate_limiter )
                          : null;

        $converter      = class_exists( 'Anime_Sync_CN_Converter' )
                          ? new Anime_Sync_CN_Converter()
                          : null;

        // Bug fix: rate_limiter 第一、id_mapper 第二，與 constructor 簽名一致
        $api_handler    = class_exists( 'Anime_Sync_API_Handler' )
                          ? new Anime_Sync_API_Handler( $rate_limiter, $id_mapper )
                          : null;

        $import_manager = ( $api_handler && class_exists( 'Anime_Sync_Import_Manager' ) )
                          ? new Anime_Sync_Import_Manager( $api_handler )
                          : null;

        // 後台管理介面
        if ( is_admin() && $import_manager && class_exists( 'Anime_Sync_Admin' ) ) {
            new Anime_Sync_Admin( $import_manager );
        }

        // Cron Manager 初始化
        if ( $import_manager && class_exists( 'Anime_Sync_Cron_Manager' ) ) {
            new Anime_Sync_Cron_Manager( $import_manager );
        }

        // 後台列表欄位
        if ( is_admin() && class_exists( 'Anime_Sync_Custom_Post_Type' ) ) {
            new Anime_Sync_Custom_Post_Type();
        }
    }

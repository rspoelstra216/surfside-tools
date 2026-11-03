<?php
/** Korean version of the full Surfside website, mobile and TV app privacy policy. */
if (!defined('ABSPATH')) { exit; }

const SURFSIDE_TOOLS_PRIVACY_KO_SCHEMA_VERSION = 1;
const SURFSIDE_TOOLS_PRIVACY_KO_SCHEMA_OPTION = 'surfside_tools_privacy_ko_schema';

function surfside_tools_privacy_policy_ko_shortcode() {
    $support_email = 'support@surfsidefellowship.org';
    $deletion_url = home_url('/account-deletion/');
    ob_start(); ?>
    <section class="surfside-privacy-policy alignwide" lang="ko" aria-label="Surfside 개인정보 처리방침">
      <p class="surfside-privacy-policy__eyebrow">Surfside Community Fellowship</p>
      <h1>개인정보 처리방침</h1>
      <p class="surfside-privacy-policy__languages"><a href="<?php echo esc_url(home_url('/privacy-policy/')); ?>" hreflang="en" lang="en">View the English privacy policy</a></p>
      <p class="surfside-privacy-policy__lead">본 개인정보 처리방침은 Surfside Community Fellowship(이하 “Surfside”)이 웹사이트(surfsidefellowship.org), 모바일 앱 및 TV 앱을 통해 정보를 수집, 이용, 공유, 보호, 보관 및 삭제하는 방식을 설명합니다.</p>
      <p class="surfside-privacy-policy__date"><strong>시행일:</strong> 2026년 11월 2일</p>

      <h2>수집하는 정보</h2>
      <h3>계정 및 프로필 정보</h3>
      <p>Surfside 계정을 만들거나 사용하는 경우 이름, 이메일 주소, 계정 식별자, 인증 정보, 계정 권한 및 계정 생성·수정 시각을 수집합니다. 이메일과 비밀번호 또는 Google 계정으로 로그인할 수 있습니다. Surfside는 사용자의 Google 비밀번호를 제공받지 않습니다.</p>
      <h3>사용자가 저장하는 콘텐츠</h3>
      <p>로그인한 사용자는 성경 구절, 설교 노트, 주석 및 비공개 기도 요청 기록을 저장할 수 있습니다. 이러한 콘텐츠는 기기 간 동기화를 위해 Surfside 계정과 연결됩니다.</p>
      <h3>문의 및 기도 요청</h3>
      <p>앱 또는 웹사이트를 통해 교회에 연락하는 경우, 사용자가 제공한 이름, 이메일 주소, 전화번호, 선호하는 연락 방법, 메시지 및 기도 요청 내용 등이 수집될 수 있습니다. 교회 기도 목록(Church Prayer List)에 제출하는 경우에는 이름 표시 방식, 요청한 활성 기간, 검토 및 처리 상태도 수집합니다.</p>
      <h3>알림 및 기기 정보</h3>
      <p>모바일 앱 알림을 켜는 경우 Expo 푸시 토큰, 기기 플랫폼, 알림 설정 및 등록 시각을 수집합니다. 앱은 성경 번역본 선택과 알림 설정 등 일부 환경설정을 기기에 로컬로 저장합니다. Surfside는 앱을 통해 정확한 위치, 연락처, 사진, 마이크 녹음, 광고 식별자 또는 결제카드 정보를 수집하지 않습니다.</p>
      <h3>웹사이트 보안 및 기술 정보</h3>
      <p>웹사이트와 서비스 제공업체는 IP 주소, 브라우저 또는 기기 정보, 요청 시각, 보안 이벤트 및 필수 쿠키 등 제한적인 기술 정보를 처리할 수 있습니다. 웹사이트의 문의 양식은 자동화된 악용을 방지하기 위해 Cloudflare Turnstile을 사용합니다.</p>
      <h3>TV 앱 및 동영상 스트리밍</h3>
      <p>Surfside는 Samsung Tizen, Roku, Amazon Fire TV, Apple TV, Google TV 등 지원되는 플랫폼에서 TV 앱을 제공합니다. TV 앱은 계정 생성이나 로그인을 요구하지 않으며, 시청자에게 이름, 이메일 주소, 전화번호, 결제 정보 또는 기타 개인정보를 입력하도록 요청하지 않습니다. Surfside는 TV 앱에서 시청자별 시청 프로필을 만들거나 맞춤형 광고를 제공하지 않습니다.</p>
      <p>TV 앱은 공개 교회 정보, 행사, 실시간 예배, 전체 예배 녹화영상 및 과거 설교를 제공하기 위해 Surfside의 WordPress 서비스와 Cloudflare Stream 및 Cloudflare R2를 포함한 동영상 전송 서비스에 요청을 보냅니다. 이러한 서비스는 콘텐츠 제공 과정에서 IP 주소, 기기 또는 앱 정보, 요청한 콘텐츠, 요청 시각, 진단 및 보안 이벤트 등의 기술 정보를 처리할 수 있습니다. Surfside는 이러한 TV 앱을 통해 시청자의 이름이나 연락처를 의도적으로 수집하지 않습니다.</p>

      <h2>정보의 이용 목적</h2>
      <ul>
        <li>인증, 계정 동기화, 저장된 콘텐츠, 기도 요청 기록 및 권한이 부여된 교회 직원용 기능을 제공합니다.</li>
        <li>교회 정보, 행사, 성경 콘텐츠, 실시간 예배, 녹화된 전체 예배, 과거 설교 및 사용자가 선택한 알림을 제공합니다.</li>
        <li>문의 및 기도 메시지를 해당 교회 직원, 목회자 또는 기도팀에 전달합니다.</li>
        <li>제출자가 선택한 방식에 따라 교회 기도 목록 게시 요청을 검토하고 게시합니다.</li>
        <li>앱, 웹사이트, 계정 및 양식을 악용으로부터 보호하고, 오류를 진단하며, 서비스를 안정적으로 운영합니다.</li>
        <li>지원, 개인정보 및 삭제 요청에 대응하고 적용되는 법적 의무를 준수합니다.</li>
      </ul>

      <h2>정보의 공유</h2>
      <p>Surfside는 개인정보를 판매하거나 맞춤형 광고를 목적으로 공유하지 않습니다. 아래 목적에 필요한 범위에서만 정보를 공개하거나 제공합니다.</p>
      <ul>
        <li><strong>교회 내 수신자:</strong> 문의 및 기도 메시지는 사용자가 선택한 분류와 공개 범위에 따라 적절한 교회 직원, 목회자 또는 기도팀에 전달됩니다.</li>
        <li><strong>교회 기도 목록:</strong> 공개를 요청한 경우 승인된 기도 내용과 사용자가 선택한 이름 또는 익명 표시가 선택한 기간 동안 앱 사용자에게 표시될 수 있습니다.</li>
        <li><strong>서비스 제공업체:</strong> Google Firebase는 인증 및 클라우드 데이터 저장을 제공하고 Google은 선택적 Google 로그인을 지원합니다. Expo는 푸시 알림 전송을 지원합니다. WordPress, 호스팅 및 이메일 서비스 제공업체는 웹사이트 운영과 메시지 전달을 지원합니다. Cloudflare는 Turnstile, Stream 및 R2 등을 통해 웹 양식 보호, 동영상 스트리밍 및 미디어 전송을 제공합니다.</li>
        <li><strong>법률 및 안전상 필요:</strong> 법률 준수, 사람의 보호, 악용 조사 또는 Surfside의 권리와 서비스를 보호하기 위해 합리적으로 필요한 경우 정보를 공개할 수 있습니다.</li>
      </ul>
      <p>웹사이트와 앱에는 YouTube, Twitch, Facebook, Google Maps, YouVersion 및 제3자 기부 페이지 등의 링크가 포함될 수 있습니다. 사용자가 해당 서비스에 직접 제공하는 정보에는 각 서비스의 개인정보 처리방침이 적용됩니다. TV 플랫폼 운영업체는 자체 방침에 따라 기기 또는 앱 정보를 별도로 처리할 수 있으며, Surfside는 이러한 독립적인 처리 방식을 통제하지 않습니다.</p>

      <h2>정보 보안</h2>
      <p>전송 중인 데이터에는 HTTPS를 사용하고, 보호되는 기능에는 인증 및 역할별 접근 제한을 적용하며, 서비스 제공업체의 접근 통제 기능을 활용합니다. 개인정보 접근은 위에 설명한 목적에 따라 필요한 직원 및 서비스 제공업체로 제한됩니다. 어떠한 보안 방식도 완벽하지 않지만, 무단 접근, 변경, 공개 또는 파기로부터 정보를 보호하기 위해 합리적인 조치를 취합니다.</p>

      <h2>보관 및 삭제</h2>
      <ul>
        <li>계정 프로필과 계정에 동기화된 콘텐츠는 계정이 존재하는 동안 보관하며 계정 삭제 시 삭제합니다.</li>
        <li>푸시 알림 등록 정보는 활성 상태인 동안 보관하고, 관련 계정 삭제, 토큰 무효 확인 또는 별도의 삭제 요청 시 제거합니다.</li>
        <li>비공개 문의 양식 및 기도 요청 이메일은 최대 12개월 동안 보관한 뒤 삭제합니다.</li>
        <li>교회 기도 목록 게시물은 사용자가 선택한 7일, 14일 또는 30일 동안 활성 상태로 유지합니다. 완료된 기록은 90일 후 삭제합니다.</li>
        <li>신원이 확인된 개인정보 및 삭제 요청은 30일 이내에 처리합니다.</li>
        <li>기기에 저장된 앱 환경설정은 사용자가 변경 또는 삭제하거나 앱을 제거할 때까지 남아 있습니다.</li>
      </ul>
      <p>서비스 제공업체는 자체 보관 일정 또는 법적 의무에 따라 제한적인 보안 로그, 백업 및 거래 로그를 보관할 수 있습니다.</p>

      <h2>사용자의 선택권 및 요청</h2>
      <p>앱에서 알림 종류를 변경하고, 교회 기도 목록에 이름을 표시할지 선택하며, 기도 요청의 활성 기간을 지정할 수 있습니다. 앱에서 Surfside 계정을 삭제하거나 앱 외부에서 도움을 요청할 수 있습니다.</p>
      <p><a class="surfside-privacy-policy__button" href="<?php echo esc_url($deletion_url); ?>">계정 및 데이터 삭제 안내 보기 (영어)</a></p>
      <p>열람, 정정, 삭제 또는 기타 개인정보 문의는 <a href="mailto:<?php echo esc_attr($support_email); ?>"><?php echo esc_html($support_email); ?></a>으로 보내 주세요. 요청을 처리하기 전에 신원 확인이 필요할 수 있습니다.</p>

      <h2>아동의 개인정보 보호</h2>
      <p>Surfside 웹사이트와 앱은 일반 교회 이용자를 대상으로 하며 아동으로부터 직접 개인정보를 수집하도록 설계되지 않았습니다. 아동이 개인정보를 제출했다고 생각되는 경우 보호자가 연락해 주시면 내용을 검토하고 적절한 경우 삭제하겠습니다.</p>

      <h2>방침의 변경</h2>
      <p>앱, 웹사이트 또는 법적 요구사항이 변경되면 본 방침을 수정할 수 있습니다. 수정된 방침은 이 페이지에 게시하고 시행일을 갱신합니다.</p>

      <h2>문의처</h2>
      <p>Surfside Community Fellowship<br>이메일: <a href="mailto:<?php echo esc_attr($support_email); ?>"><?php echo esc_html($support_email); ?></a><br>전화: <a href="tel:+13216072111">(321) 607-2111</a></p>
    </section>
    <?php echo surfside_tools_privacy_policy_styles(); ?>
    <?php return ob_get_clean();
}
add_shortcode('surfside_privacy_policy_ko','surfside_tools_privacy_policy_ko_shortcode');

/** Register the Korean page once per schema version, on admin access only. */
function surfside_tools_provision_privacy_policy_ko_page(){
    if(!is_admin())return;
    if((int)get_option(SURFSIDE_TOOLS_PRIVACY_KO_SCHEMA_OPTION,0)>=SURFSIDE_TOOLS_PRIVACY_KO_SCHEMA_VERSION)return;
    $page=get_page_by_path('privacy-policy-ko',OBJECT,'page');
    $changed=false;
    if($page instanceof WP_Post){
        $content=(string)$page->post_content;
        // Preserve custom editorial content if a page already uses this URL.
        $managed=trim($content)===''||has_shortcode($content,'surfside_privacy_policy_ko');
        if($managed&&((string)$page->post_title!=='개인정보 처리방침 (한국어)'||trim($content)!=='[surfside_privacy_policy_ko]'||(string)$page->post_status!=='publish')){
            $result=wp_update_post(array('ID'=>(int)$page->ID,'post_title'=>'개인정보 처리방침 (한국어)','post_content'=>'[surfside_privacy_policy_ko]','post_status'=>'publish'),true);
            if(is_wp_error($result))return;
            $changed=true;
        }
    }else{
        $result=wp_insert_post(array('post_title'=>'개인정보 처리방침 (한국어)','post_name'=>'privacy-policy-ko','post_content'=>'[surfside_privacy_policy_ko]','post_status'=>'publish','post_type'=>'page'),true);
        if(is_wp_error($result)||!$result)return;
        $changed=true;
    }
    update_option(SURFSIDE_TOOLS_PRIVACY_KO_SCHEMA_OPTION,SURFSIDE_TOOLS_PRIVACY_KO_SCHEMA_VERSION,false);
    if($changed)flush_rewrite_rules(false);
}
add_action('admin_init','surfside_tools_provision_privacy_policy_ko_page',8);

import Styled from 'styled-components';
import chevronIcon from '@icon/chevron-down-solid.svg';

const SidebarStyle = Styled.div`
    min-width: 256px;
    min-height: 100vh;
    background: var(--templatiq-white-color);
    box-shadow: 0 7px 20px 5px var(--templatiq-dark-color)05;
    .templatiq__sidebar__top {
        display: flex;
        gap: 20px;
        justify-content: space-between;
        padding: 10px 24px;
        background: var(--templatiq-section-color2);
        .templatiq__sidebar__top__title {
            display: flex;
            gap: 10px;
            align-items: center;
            font-size: 14px;
            font-weight: 600;
            color: var(--templatiq-gray-color);
            margin: 0;
            path {
                fill: var(--templatiq-gray-color);
            }
        }
        .templatiq__sidebar__top__clear {
            font-size: 13px;
            font-weight: 500;
            color: var(--templatiq-primary-color);
            box-shadow: none;
        }
    }

    .templatiq__sidebar__wrapper {
        padding-bottom: 30px
    }
    
    .templatiq__sidebar__nav {
        display: flex;
        gap: 24px;
        padding: 0 24px;
        margin-bottom: 24px;
        border-bottom: 1px solid var(--templatiq-sec-color);
        .templatiq__sidebar__nav__item {
            &.react-tabs__tab--selected {
                .templatiq__sidebar__nav__link {
                    color: var(--templatiq-primary-color);
                    &:before {
                        width: 100%;
                        background: var(--templatiq-primary-color);
                    }
                }
            }
        }
        .templatiq__sidebar__nav__link {
            position: relative;
            display: flex;
            gap: 8px;
            align-items: center;
            font-size: 14px;
            font-weight: 500;
            color: var(--templatiq-body-color);
            height: 50px;
            box-shadow: none;
            text-decoration: none;
            &:before {
                content: '';
                position: absolute;
                bottom: 0;
                height: 2px;
                width: 0;
                background: transparent;
                transition: width 0.3s ease;
            }
            &:hover {
                color: var(--templatiq-primary-color);
                &:before {
                    width: 100%;
                    background: var(--templatiq-primary-color);
                }
            }
        }
    }

    .templatiq__sidebar__nav--admin {
        flex-direction: column;
        gap: 0;
        margin: 0;
        padding: 0;
        border: none;
        .templatiq__sidebar__nav__link {
            height: 46px;
            gap: 12px;
            position: relative;
            background: transparent;
            padding-inline-start: 24px;
            transition: background 0.3s ease;
            path {
                fill: var(--templatiq-body-color);
                transition: fill 0.3s ease;
            }
            &:before {
                width: 2px;
                height: 0;
                top: 0;
                inset-inline-end: 0;
            }
            &:hover,
            &.active {
                color: var(--templatiq-primary-color);
                background: var(--templatiq-primary-transparent);
                path {
                    fill: var(--templatiq-primary-color);
                }
                &:before {
                    width: 2px;
                    height: 100%;
                    background: var(--templatiq-primary-color);
                }
            }
        }
    }
`;

const SidebarItemStyle = Styled.div`
    display: flex;
    gap: 15px;
    padding: 0 24px;
    flex-direction: column;
    
    .templatiq__sidebar__accordion {    
        display: flex;
        gap: 25px;
        flex-direction: column;
    }
    .szh-accordion__item-heading {
        font-size: 14px;
        font-weight: 500;
    }
    .szh-accordion__item-btn {
        position: relative;
        width: 100%;
        padding: 0;
        text-align: start;
        background: transparent;
        cursor: pointer;
        &:before {
            content: '';
            position: absolute;
            inset-inline-end: 0;
            top: 2px;
            width: 14px;
            height: 14px;
            background: url(${chevronIcon}) no-repeat center center;
            background-size: 14px;
            transition: transform 0.3s ease;
        }
        &[aria-expanded="true"] {
            margin: 0 0 20px;
            &:before {
                transform: rotate(180deg);
            }
        }
    }
`;

const AdminSidebarStyle = Styled.div`
    min-width: 256px;
    background: var(--templatiq-white-color);
    box-shadow: 0 7px 20px 5px var(--templatiq-dark-color)05;
    
    .templatiq__sidebar__nav {
        display: flex;
        flex-direction: column;
        height: 100vh;
        .templatiq__sidebar__nav__link {
            position: relative;
            display: flex;
            gap: 12px;
            align-items: center;
            font-size: 14px;
            font-weight: 500;
            height: 46px;
            background: transparent;
            padding-inline-start: 24px;
            color: var(--templatiq-body-color);
            box-shadow: none;
            text-decoration: none;
            transition: background 0.3s ease;
            path {
                fill: var(--templatiq-body-color);
                transition: fill 0.3s ease;
            }
            &:before {
                content: '';
                position: absolute;
                width: 2px;
                height: 0;
                top: 0;
                inset-inline-end: 0;
                transition: width 0.3s ease;
            }
            &.active,
            &:hover {
                color: var(--templatiq-primary-color);
                background: var(--templatiq-primary-transparent);
                path {
                    fill: var(--templatiq-primary-color);
                }
                &:before {
                    height: 100%;
                    background: var(--templatiq-primary-color);
                
                }
            }
        }
    }
`;


export { SidebarStyle, SidebarItemStyle, AdminSidebarStyle };